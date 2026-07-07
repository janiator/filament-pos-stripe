<?php

namespace App\Filament\Actions;

use App\Enums\AddonType;
use App\Enums\PowerOfficeSyncRunStatus;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\PowerOfficeSyncRun;
use App\Services\PowerOffice\PowerOfficeSyncPreviewService;
use App\Support\PowerOffice\PowerOfficePostingSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class PowerOfficeVoucherPreviewAction
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function syncConfirmationFormSchema(callable $resolveSession): array
    {
        return [
            Hidden::make('_record_id'),
            ViewField::make('voucher_preview')
                ->view('filament.components.power-office-voucher-preview')
                ->viewData(fn (Get $get): array => [
                    'preview' => self::decodePreviewJson((string) ($get('preview_json') ?? '')),
                ])
                ->key(fn (Get $get): string => md5((string) ($get('preview_json') ?? ''))),
            Toggle::make('resolve_poweroffice_accounts')
                ->label(__('Resolve PowerOffice account IDs (calls PowerOffice API)'))
                ->helperText(__('Resolves each GL account number and includes the JSON body for POST manual journal.'))
                ->default(false)
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($resolveSession): void {
                    $session = self::sessionFromForm($resolveSession, $get);
                    if (! $session instanceof PosSession) {
                        return;
                    }

                    $set('preview_json', self::encodePreview($session, (bool) $state));
                }),
            Hidden::make('preview_json'),
        ];
    }

    public static function fillFormForSession(PosSession $session, bool $resolveAccounts = false): array
    {
        return [
            '_record_id' => $session->getKey(),
            'resolve_poweroffice_accounts' => $resolveAccounts,
            'preview_json' => self::encodePreview($session, $resolveAccounts),
        ];
    }

    public static function modalHeadingForSession(PosSession $session): string
    {
        $run = self::latestSuccessfulRun($session);

        return $run !== null
            ? (string) __('Re-sync PowerOffice')
            : (string) __('Sync PowerOffice');
    }

    public static function modalDescriptionForSession(PosSession $session): string
    {
        $run = self::latestSuccessfulRun($session);
        if ($run === null) {
            return (string) __('Review the voucher lines below before posting to PowerOffice.');
        }

        $voucherLabel = $run->journal_voucher_no
            ? 'bilagsnr #'.$run->journal_voucher_no
            : 'the existing voucher';

        return (string) __(
            PowerOfficePostingSettings::usesDirectPosting($session->store->powerOfficeIntegration)
                ? 'The :voucher will be reversed in PowerOffice and a new voucher will be posted from the current Z-report. Review the lines below before continuing.'
                : 'The :voucher journal-entry draft will be deleted in PowerOffice and a new draft will be created from the current Z-report. Review the lines below before continuing.',
            ['voucher' => $voucherLabel],
        );
    }

    public static function canSync(PosSession $session): bool
    {
        if ($session->status !== 'closed') {
            return false;
        }

        $tenant = Filament::getTenant();
        if (! $tenant || ! Addon::storeHasActiveAddon($tenant->getKey(), AddonType::PowerOfficeGo)) {
            return false;
        }

        $session->loadMissing('store.powerOfficeIntegration');
        $integration = $session->store?->powerOfficeIntegration;

        return (bool) ($integration?->isConnected() && $integration->sync_enabled);
    }

    public static function encodePreview(PosSession $session, bool $resolveAccounts = false): string
    {
        $session->loadMissing('store.powerOfficeIntegration');
        $integration = $session->store?->powerOfficeIntegration;
        if (! $integration) {
            return json_encode([
                'ok' => false,
                'error' => 'PowerOffice integration is not configured for this store.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $payload = app(PowerOfficeSyncPreviewService::class)->previewZReport($session, $integration, $resolveAccounts);

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function decodePreviewJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'Preview unavailable.'];
    }

    protected static function sessionFromForm(callable $resolveSession, Get $get): ?PosSession
    {
        $recordId = $get('_record_id');
        if (is_numeric($recordId) && (int) $recordId > 0) {
            $session = PosSession::query()->find((int) $recordId);

            return $session instanceof PosSession ? $session : null;
        }

        $session = $resolveSession();

        return $session instanceof PosSession ? $session : null;
    }

    protected static function latestSuccessfulRun(PosSession $session): ?PowerOfficeSyncRun
    {
        return PowerOfficeSyncRun::query()
            ->where('pos_session_id', $session->id)
            ->where('status', PowerOfficeSyncRunStatus::Success)
            ->latest('id')
            ->first();
    }
}
