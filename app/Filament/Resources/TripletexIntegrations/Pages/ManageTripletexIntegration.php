<?php

namespace App\Filament\Resources\TripletexIntegrations\Pages;

use App\Enums\PowerOfficeMappingBasis;
use App\Enums\TripletexIntegrationStatus;
use App\Filament\Resources\TripletexIntegrations\Schemas\TripletexIntegrationForm;
use App\Filament\Resources\TripletexIntegrations\TripletexIntegrationResource;
use App\Filament\Resources\TripletexSyncRuns\TripletexSyncRunResource;
use App\Jobs\SyncTripletexZReportJob;
use App\Models\PosSession;
use App\Models\StoreStripePayout;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use App\Services\Tripletex\TripletexApiClient;
use App\Services\Tripletex\TripletexHistoricalSyncService;
use App\Services\Tripletex\TripletexSyncPreviewService;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use App\Support\Tripletex\TripletexMeranoLegacyFormDefaults;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ManageTripletexIntegration extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $resource = TripletexIntegrationResource::class;

    protected string $view = 'filament.resources.tripletex-integrations.pages.manage-tripletex-integration';

    public ?TripletexIntegration $integration = null;

    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $tripletexPreview = null;

    public function mount(): void
    {
        abort_unless(TripletexIntegrationResource::canAccess(), 403);

        $store = Filament::getTenant();
        abort_unless($store, 403);

        $this->integration = TripletexIntegration::query()->firstOrCreate(
            ['store_id' => $store->getKey()],
            [],
        );

        $this->fillSettingsForm();
        $this->tripletexPreview = null;
    }

    public function clearTripletexPreview(): void
    {
        $this->tripletexPreview = null;
    }

    public function getTitle(): string
    {
        return 'Tripletex';
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->integration) {
            return $schema->components([]);
        }

        return TripletexIntegrationForm::configure(
            $schema->model($this->integration)->statePath('data'),
        );
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function saveSettings(): void
    {
        abort_unless($this->integration, 403);

        $data = $this->form->getState();

        $status = TripletexIntegrationStatus::NotConnected;
        if (filled($data['consumer_token'] ?? null) && filled($data['employee_token'] ?? null)) {
            $status = TripletexIntegrationStatus::Connected;
        }

        $this->integration->update([
            'environment' => $data['environment'],
            'consumer_token' => $data['consumer_token'] ?? null,
            'employee_token' => $data['employee_token'] ?? null,
            'status' => $status,
            'mapping_basis' => $data['mapping_basis'],
            'sync_enabled' => (bool) ($data['sync_enabled'] ?? true),
            'auto_sync_on_z_report' => (bool) ($data['auto_sync_on_z_report'] ?? true),
            'auto_sync_payouts' => (bool) ($data['auto_sync_payouts'] ?? false),
            'z_report_include_settlement' => (bool) ($data['z_report_include_settlement'] ?? false),
        ]);

        $this->integration->refresh();

        $this->saveMappingsForBasis($data);
        $this->persistLedgerSettingsFromForm($data);

        Notification::make()
            ->title(__('Saved'))
            ->success()
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, TripletexSyncRun>
     */
    public function recentSyncRuns(): Collection
    {
        if (! $this->integration) {
            return collect();
        }

        return TripletexSyncRun::query()
            ->where('store_id', $this->integration->store_id)
            ->latest('id')
            ->limit(15)
            ->get();
    }

    protected function fillSettingsForm(): void
    {
        $this->integration?->load('accountMappings');

        if (! $this->integration) {
            return;
        }

        $base = [
            'environment' => $this->integration->environment->value,
            'consumer_token' => $this->integration->consumer_token,
            'employee_token' => $this->integration->employee_token,
            'mapping_basis' => $this->integration->mapping_basis->value,
            'sync_enabled' => $this->integration->sync_enabled,
            'auto_sync_on_z_report' => $this->integration->auto_sync_on_z_report,
            'auto_sync_payouts' => $this->integration->auto_sync_payouts,
            'z_report_include_settlement' => $this->integration->z_report_include_settlement,
        ];

        if ($this->integration->mapping_basis === PowerOfficeMappingBasis::Vat) {
            $shared = $this->extractSharedAccountsFromMappings($this->integration->accountMappings);
            foreach (PowerOfficeStandardVatRates::basisKeys() as $vatKey) {
                $base['vat_sales_'.$vatKey] = $this->integration->accountMappings
                    ->firstWhere('basis_key', $vatKey)
                    ?->sales_account_no ?? '';
            }
            $base = array_merge($base, $this->sharedAccountsToLedgerFormKeys($shared));
            $base['mappings'] = [];
            $base = array_merge($base, $this->ledgerFormStateFromIntegration());
            $base = TripletexMeranoLegacyFormDefaults::mergeWhenPristine($base, $this->integration);
            $this->form->fill($base);

            return;
        }

        $base['mappings'] = $this->integration->accountMappings->map(fn (TripletexAccountMapping $m): array => [
            'basis_key' => $m->basis_key,
            'basis_label' => $m->basis_label,
            'sales_account_no' => $m->sales_account_no,
            'vat_account_no' => $m->vat_account_no,
            'tips_account_no' => $m->tips_account_no,
            'cash_account_no' => $m->cash_account_no,
            'card_clearing_account_no' => $m->card_clearing_account_no,
            'fees_account_no' => $m->fees_account_no,
            'rounding_account_no' => $m->rounding_account_no,
            'is_active' => $m->is_active,
        ])->values()->all();

        $base = array_merge($base, $this->ledgerFormStateFromIntegration());
        $base = TripletexMeranoLegacyFormDefaults::mergeWhenPristine($base, $this->integration);

        $this->form->fill($base);
    }

    /**
     * @return array<string, string>
     */
    protected function ledgerFormStateFromIntegration(): array
    {
        $l = $this->integration->settings['ledger'] ?? [];
        if (! is_array($l)) {
            $l = [];
        }
        $pd = is_array($l['payment_debits'] ?? null) ? $l['payment_debits'] : [];
        $pf = is_array($l['payment_fee'] ?? null) ? $l['payment_fee'] : [];
        $po = is_array($l['payout'] ?? null) ? $l['payout'] : [];
        $appFee = is_array($l['application_fee'] ?? null) ? $l['application_fee'] : [];
        $vatSales = is_array($l['tripletex_vat_type_sales'] ?? null) ? $l['tripletex_vat_type_sales'] : [];
        $ext = is_array($l['external_ticket_sales'] ?? null) ? $l['external_ticket_sales'] : [];
        $extMetaKeys = is_array($ext['require_metadata_keys'] ?? null) ? $ext['require_metadata_keys'] : [];
        $extMetaKeysStr = $extMetaKeys !== []
            ? implode(', ', array_map(static fn ($k): string => (string) $k, $extMetaKeys))
            : '';

        $out = [
            'ledger_default_sales_account_no' => (string) ($l['default_sales_account_no'] ?? ''),
            'ledger_payment_debit_cash' => (string) ($pd['cash'] ?? ''),
            'ledger_payment_debit_card_present' => (string) ($pd['card_present'] ?? ''),
            'ledger_payment_debit_card' => (string) ($pd['card'] ?? ''),
            'ledger_payment_debit_vipps' => (string) ($pd['vipps'] ?? ''),
            'ledger_payment_debit_mobile' => (string) ($pd['mobile'] ?? ''),
            'ledger_payment_debit_gift_token' => (string) ($pd['gift_token'] ?? ''),
            'ledger_payment_debit_default' => (string) ($pd['default'] ?? ''),
            'ledger_giftcard_liability_account_no' => (string) ($l['giftcard_liability_account_no'] ?? ''),
            'ledger_interim_liquid_account_no' => (string) ($l['interim_liquid_account_no'] ?? ''),
            'ledger_fee_credit_account_no' => (string) ($pf['credit_account_no'] ?? ''),
            'ledger_fee_debit_account_no' => (string) ($pf['debit_account_no'] ?? ''),
            'ledger_payout_credit_account_no' => (string) ($po['credit_account_no'] ?? ''),
            'ledger_payout_debit_bank_account_no' => (string) ($po['debit_bank_account_no'] ?? ''),
            'ledger_application_fee_debit_account_no' => (string) ($appFee['debit_account_no'] ?? ''),
            'ledger_app_fee_supplier_id' => (string) ($l['app_fee_supplier_id'] ?? ''),
            'ledger_tripletex_vat_output_vat' => (string) ($l['tripletex_vat_type_output_vat'] ?? ''),
            'ledger_z_report_split_lines_by_calendar_day' => (bool) ($l['z_report_split_lines_by_calendar_day'] ?? false),
            'ledger_external_ticket_sales_enabled' => (bool) ($ext['enabled'] ?? false),
            'ledger_external_ticket_sales_account_no' => (string) ($ext['sales_account_no'] ?? ''),
            'ledger_external_ticket_clearing_account_no' => (string) ($ext['clearing_account_no'] ?? ''),
            'ledger_external_ticket_vat_type_id' => (string) ($ext['tripletex_vat_type_id'] ?? ''),
            'ledger_external_ticket_metadata_keys' => $extMetaKeysStr,
            'ledger_external_ticket_description_regex' => (string) ($ext['description_regex'] ?? ''),
        ];

        foreach (PowerOfficeStandardVatRates::basisKeys() as $vatKey) {
            $out['ledger_tripletex_vat_sales_'.$vatKey] = (string) ($vatSales[$vatKey] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistLedgerSettingsFromForm(array $data): void
    {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        $settings = is_array($integration->settings) ? $integration->settings : [];

        $paymentDebits = [];
        foreach ([
            'cash' => 'ledger_payment_debit_cash',
            'card_present' => 'ledger_payment_debit_card_present',
            'card' => 'ledger_payment_debit_card',
            'vipps' => 'ledger_payment_debit_vipps',
            'mobile' => 'ledger_payment_debit_mobile',
            'gift_token' => 'ledger_payment_debit_gift_token',
            'default' => 'ledger_payment_debit_default',
        ] as $key => $formKey) {
            $v = trim((string) ($data[$formKey] ?? ''));
            if ($v !== '') {
                $paymentDebits[$key] = $v;
            }
        }

        $ledger = [];

        $defaultSales = trim((string) ($data['ledger_default_sales_account_no'] ?? ''));
        if ($defaultSales !== '') {
            $ledger['default_sales_account_no'] = $defaultSales;
        }

        if ($paymentDebits !== []) {
            $ledger['payment_debits'] = $paymentDebits;
        }

        $gift = trim((string) ($data['ledger_giftcard_liability_account_no'] ?? ''));
        if ($gift !== '') {
            $ledger['giftcard_liability_account_no'] = $gift;
        }

        $interim = trim((string) ($data['ledger_interim_liquid_account_no'] ?? ''));
        if ($interim !== '') {
            $ledger['interim_liquid_account_no'] = $interim;
        }

        $feeCredit = trim((string) ($data['ledger_fee_credit_account_no'] ?? ''));
        $feeDebit = trim((string) ($data['ledger_fee_debit_account_no'] ?? ''));
        if ($feeCredit !== '' && $feeDebit !== '') {
            $ledger['payment_fee'] = [
                'credit_account_no' => $feeCredit,
                'debit_account_no' => $feeDebit,
            ];
        }

        $payoutCredit = trim((string) ($data['ledger_payout_credit_account_no'] ?? ''));
        $payoutBank = trim((string) ($data['ledger_payout_debit_bank_account_no'] ?? ''));
        if ($payoutCredit !== '' && $payoutBank !== '') {
            $ledger['payout'] = [
                'credit_account_no' => $payoutCredit,
                'debit_bank_account_no' => $payoutBank,
            ];
        }

        $appFeeDebit = trim((string) ($data['ledger_application_fee_debit_account_no'] ?? ''));
        if ($appFeeDebit !== '') {
            $ledger['application_fee'] = [
                'debit_account_no' => $appFeeDebit,
            ];
        }

        $supplierId = trim((string) ($data['ledger_app_fee_supplier_id'] ?? ''));
        if ($supplierId !== '') {
            $ledger['app_fee_supplier_id'] = (int) $supplierId;
        }

        $vatSales = [];
        foreach (PowerOfficeStandardVatRates::basisKeys() as $vatKey) {
            $v = trim((string) ($data['ledger_tripletex_vat_sales_'.$vatKey] ?? ''));
            if ($v !== '') {
                $vatSales[(string) $vatKey] = (int) $v;
            }
        }
        if ($vatSales !== []) {
            $ledger['tripletex_vat_type_sales'] = $vatSales;
        }

        $outVat = trim((string) ($data['ledger_tripletex_vat_output_vat'] ?? ''));
        if ($outVat !== '') {
            $ledger['tripletex_vat_type_output_vat'] = (int) $outVat;
        }

        $ledger['z_report_split_lines_by_calendar_day'] = (bool) ($data['ledger_z_report_split_lines_by_calendar_day'] ?? false);

        $extOn = (bool) ($data['ledger_external_ticket_sales_enabled'] ?? false);
        $salesNo = trim((string) ($data['ledger_external_ticket_sales_account_no'] ?? ''));
        if ($extOn && $salesNo !== '') {
            $extBlock = [
                'enabled' => true,
                'sales_account_no' => $salesNo,
            ];
            $clr = trim((string) ($data['ledger_external_ticket_clearing_account_no'] ?? ''));
            if ($clr !== '') {
                $extBlock['clearing_account_no'] = $clr;
            }
            $vatId = trim((string) ($data['ledger_external_ticket_vat_type_id'] ?? ''));
            if ($vatId !== '') {
                $extBlock['tripletex_vat_type_id'] = (int) $vatId;
            }
            $keysRaw = trim((string) ($data['ledger_external_ticket_metadata_keys'] ?? ''));
            if ($keysRaw !== '') {
                $extBlock['require_metadata_keys'] = array_values(array_filter(array_map('trim', explode(',', $keysRaw))));
            }
            $regex = trim((string) ($data['ledger_external_ticket_description_regex'] ?? ''));
            if ($regex !== '') {
                $extBlock['description_regex'] = $regex;
            }
            $ledger['external_ticket_sales'] = $extBlock;
        } else {
            $ledger['external_ticket_sales'] = [
                'enabled' => false,
            ];
        }

        $settings['ledger'] = $ledger;
        $integration->update(['settings' => $settings]);
        $this->integration = $integration;
    }

    /**
     * @param  Collection<int, TripletexAccountMapping>  $mappings
     * @return array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}
     */
    protected function extractSharedAccountsFromMappings(Collection $mappings): array
    {
        $first = $mappings->first();
        if (! $first instanceof TripletexAccountMapping) {
            return [
                'vat_account_no' => null,
                'tips_account_no' => null,
                'cash_account_no' => null,
                'card_clearing_account_no' => null,
                'rounding_account_no' => null,
            ];
        }

        return [
            'vat_account_no' => $first->vat_account_no,
            'tips_account_no' => $first->tips_account_no,
            'cash_account_no' => $first->cash_account_no,
            'card_clearing_account_no' => $first->card_clearing_account_no,
            'rounding_account_no' => $first->rounding_account_no,
        ];
    }

    /**
     * @param  array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}  $shared
     * @return array<string, string>
     */
    protected function sharedAccountsToLedgerFormKeys(array $shared): array
    {
        return [
            'ledger_shared_vat_account_no' => (string) ($shared['vat_account_no'] ?? ''),
            'ledger_shared_tips_account_no' => (string) ($shared['tips_account_no'] ?? ''),
            'ledger_shared_cash_account_no' => (string) ($shared['cash_account_no'] ?? ''),
            'ledger_shared_card_clearing_account_no' => (string) ($shared['card_clearing_account_no'] ?? ''),
            'ledger_shared_rounding_account_no' => (string) ($shared['rounding_account_no'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}
     */
    protected function ledgerSharedAccountsFromFormData(array $data): array
    {
        return [
            'vat_account_no' => filled($data['ledger_shared_vat_account_no'] ?? null) ? (string) $data['ledger_shared_vat_account_no'] : null,
            'tips_account_no' => filled($data['ledger_shared_tips_account_no'] ?? null) ? (string) $data['ledger_shared_tips_account_no'] : null,
            'cash_account_no' => filled($data['ledger_shared_cash_account_no'] ?? null) ? (string) $data['ledger_shared_cash_account_no'] : null,
            'card_clearing_account_no' => filled($data['ledger_shared_card_clearing_account_no'] ?? null) ? (string) $data['ledger_shared_card_clearing_account_no'] : null,
            'rounding_account_no' => filled($data['ledger_shared_rounding_account_no'] ?? null) ? (string) $data['ledger_shared_rounding_account_no'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveMappingsForBasis(array $data): void
    {
        $basis = PowerOfficeMappingBasis::tryFrom((string) ($data['mapping_basis'] ?? ''));
        if ($basis === PowerOfficeMappingBasis::Vat) {
            $this->saveVatBasisMappings($data);

            return;
        }

        $this->saveMappingsFromRepeater($data['mappings'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveVatBasisMappings(array $data): void
    {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        $shared = $this->ledgerSharedAccountsFromFormData($data);

        TripletexAccountMapping::query()
            ->where('tripletex_integration_id', $integration->getKey())
            ->delete();

        foreach (PowerOfficeStandardVatRates::basisKeys() as $vatKey) {
            $sales = trim((string) ($data['vat_sales_'.$vatKey] ?? ''));
            if ($sales === '') {
                continue;
            }

            TripletexAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'tripletex_integration_id' => $integration->getKey(),
                'basis_type' => PowerOfficeMappingBasis::Vat,
                'basis_key' => $vatKey,
                'basis_label' => null,
                'sales_account_no' => $sales,
                'vat_account_no' => $shared['vat_account_no'],
                'tips_account_no' => $shared['tips_account_no'],
                'cash_account_no' => $shared['cash_account_no'],
                'card_clearing_account_no' => $shared['card_clearing_account_no'],
                'fees_account_no' => null,
                'rounding_account_no' => $shared['rounding_account_no'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function saveMappingsFromRepeater(array $rows): void
    {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        TripletexAccountMapping::query()
            ->where('tripletex_integration_id', $integration->getKey())
            ->delete();

        foreach ($rows as $row) {
            if (empty($row['basis_key']) || empty($row['sales_account_no'])) {
                continue;
            }

            TripletexAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'tripletex_integration_id' => $integration->getKey(),
                'basis_type' => $integration->mapping_basis,
                'basis_key' => (string) $row['basis_key'],
                'basis_label' => $row['basis_label'] ?? null,
                'sales_account_no' => (string) $row['sales_account_no'],
                'vat_account_no' => filled($row['vat_account_no'] ?? null) ? (string) $row['vat_account_no'] : null,
                'tips_account_no' => filled($row['tips_account_no'] ?? null) ? (string) $row['tips_account_no'] : null,
                'cash_account_no' => filled($row['cash_account_no'] ?? null) ? (string) $row['cash_account_no'] : null,
                'card_clearing_account_no' => filled($row['card_clearing_account_no'] ?? null) ? (string) $row['card_clearing_account_no'] : null,
                'fees_account_no' => filled($row['fees_account_no'] ?? null) ? (string) $row['fees_account_no'] : null,
                'rounding_account_no' => filled($row['rounding_account_no'] ?? null) ? (string) $row['rounding_account_no'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->submit('saveSettings'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function hasFullWidthFormActions(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncHistory')
                ->label(__('Sync history'))
                ->url(fn (): string => TripletexSyncRunResource::getUrl('index'))
                ->color('gray'),
            Action::make('previewLatestZ')
                ->label(__('Preview latest Z voucher'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->slideOver()
                ->modalHeading(__('Preview latest closed session (Z)'))
                ->modalDescription(__('Shows ledger lines for the most recently closed session. Turn on account resolution to build the exact JSON body sent to Tripletex (calls their API).'))
                ->modalWidth('xl')
                ->visible(fn (): bool => $this->integration?->isConnected() ?? false)
                ->form([
                    Toggle::make('resolve_tripletex_accounts')
                        ->label(__('Resolve Tripletex account IDs (calls Tripletex API)'))
                        ->helperText(__('When enabled, session token + ledger/account lookups run; the draft voucher payload is included in the on-page preview.'))
                        ->default(false),
                ])
                ->action(function (array $data, TripletexSyncPreviewService $preview): void {
                    $store = Filament::getTenant();
                    if (! $store || ! $this->integration) {
                        return;
                    }
                    $session = PosSession::query()
                        ->where('store_id', $store->getKey())
                        ->where('status', 'closed')
                        ->whereNotNull('closing_data')
                        ->orderByDesc('closed_at')
                        ->first();
                    if (! $session) {
                        Notification::make()->title(__('No closed sessions'))->warning()->send();
                        $this->tripletexPreview = null;

                        return;
                    }
                    $this->tripletexPreview = $preview->previewZReport(
                        $session,
                        $this->integration,
                        (bool) ($data['resolve_tripletex_accounts'] ?? false),
                    );
                    Notification::make()->title(__('Z voucher preview ready'))->body('Scroll to the preview section below.')->success()->send();
                }),
            Action::make('previewLatestPayout')
                ->label(__('Preview latest payout voucher'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->slideOver()
                ->modalHeading(__('Preview latest paid payout'))
                ->modalDescription(__('Shows ledger lines for the most recent paid Stripe payout. Turn on account resolution to build the exact Tripletex voucher JSON (calls their API).'))
                ->modalWidth('xl')
                ->visible(fn (): bool => $this->integration?->isConnected() ?? false)
                ->form([
                    Toggle::make('resolve_tripletex_accounts')
                        ->label(__('Resolve Tripletex account IDs (calls Tripletex API)'))
                        ->helperText(__('When enabled, session token + ledger/account lookups run; the draft voucher payload is included in the on-page preview.'))
                        ->default(false),
                ])
                ->action(function (array $data, TripletexSyncPreviewService $preview): void {
                    $store = Filament::getTenant();
                    if (! $store || ! $this->integration) {
                        return;
                    }
                    $payout = StoreStripePayout::query()
                        ->where('store_id', $store->getKey())
                        ->where('status', 'paid')
                        ->orderByDesc('arrival_date')
                        ->first();
                    if (! $payout) {
                        Notification::make()->title(__('No paid payouts'))->warning()->send();
                        $this->tripletexPreview = null;

                        return;
                    }
                    $this->tripletexPreview = $preview->previewPayout(
                        $payout,
                        $this->integration,
                        (bool) ($data['resolve_tripletex_accounts'] ?? false),
                    );
                    Notification::make()->title(__('Payout voucher preview ready'))->body('Scroll to the preview section below.')->success()->send();
                }),
            Action::make('historicalZReports')
                ->label(__('Queue historical Z-reports'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->integration?->sync_enabled)
                ->form([
                    DatePicker::make('from')->label(__('Closed from (optional)')),
                    DatePicker::make('to')->label(__('Closed to (optional)')),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(25)
                        ->minValue(1)
                        ->maxValue(500)
                        ->required(),
                    Toggle::make('only_missing')
                        ->label(__('Skip sessions already synced successfully'))
                        ->default(true),
                ])
                ->action(function (array $data, TripletexHistoricalSyncService $historical): void {
                    $store = Filament::getTenant();
                    if (! $store || ! $this->integration) {
                        return;
                    }
                    $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                    $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null;
                    $limit = (int) ($data['limit'] ?? 25);
                    $onlyMissing = (bool) ($data['only_missing'] ?? true);
                    $result = $historical->queueZReports($store, $from, $to, $limit, $onlyMissing);
                    Notification::make()
                        ->title(__('Historical Z-reports queued'))
                        ->body("Queued {$result['queued']}, skipped ineligible {$result['skipped']}.")
                        ->success()
                        ->send();
                }),
            Action::make('historicalPayouts')
                ->label(__('Queue historical payouts'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->integration?->sync_enabled)
                ->form([
                    DatePicker::make('from')->label(__('Arrival from (optional)')),
                    DatePicker::make('to')->label(__('Arrival to (optional)')),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(25)
                        ->minValue(1)
                        ->maxValue(500)
                        ->required(),
                    Toggle::make('only_missing')
                        ->label(__('Skip payouts already synced successfully'))
                        ->default(true),
                ])
                ->action(function (array $data, TripletexHistoricalSyncService $historical): void {
                    $store = Filament::getTenant();
                    if (! $store || ! $this->integration) {
                        return;
                    }
                    $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                    $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null;
                    $limit = (int) ($data['limit'] ?? 25);
                    $onlyMissing = (bool) ($data['only_missing'] ?? true);
                    $result = $historical->queuePayouts($store, $from, $to, $limit, $onlyMissing);
                    Notification::make()
                        ->title(__('Historical payouts queued'))
                        ->body("Queued {$result['queued']} payout job(s).")
                        ->success()
                        ->send();
                }),
            Action::make('testConnection')
                ->label(__('Test Tripletex connection'))
                ->visible(fn (): bool => $this->integration?->isConnected() ?? false)
                ->action(function (TripletexApiClient $api): void {
                    try {
                        $api->createSessionToken($this->integration);
                        Notification::make()->title(__('Tripletex session OK'))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Tripletex connection failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('syncLatestZReport')
                ->label(__('Queue latest Z-report sync'))
                ->visible(fn (): bool => (bool) $this->integration?->sync_enabled)
                ->action(function (): void {
                    $store = Filament::getTenant();
                    if (! $store) {
                        return;
                    }
                    $session = PosSession::query()
                        ->where('store_id', $store->getKey())
                        ->where('status', 'closed')
                        ->whereNotNull('closing_data')
                        ->orderByDesc('closed_at')
                        ->first();
                    if (! $session) {
                        Notification::make()->title(__('No closed sessions'))->warning()->send();

                        return;
                    }
                    SyncTripletexZReportJob::dispatch($session->id, true);
                    Notification::make()->title(__('Queued Tripletex Z-report sync'))->success()->send();
                }),
        ];
    }
}
