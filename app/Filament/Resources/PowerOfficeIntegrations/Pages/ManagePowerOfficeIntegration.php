<?php

namespace App\Filament\Resources\PowerOfficeIntegrations\Pages;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Resources\PowerOfficeIntegrations\PowerOfficeIntegrationResource;
use App\Jobs\SyncPowerOfficeZReportJob;
use App\Models\Collection as ProductCollection;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\PowerOfficeSyncRun;
use App\Models\Vendor;
use App\Services\PowerOffice\PowerOfficeOnboardingService;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ManagePowerOfficeIntegration extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $resource = PowerOfficeIntegrationResource::class;

    protected string $view = 'filament.resources.power-office-integrations.pages.manage-power-office-integration';

    public ?PowerOfficeIntegration $integration = null;

    public ?array $data = [];

    public int $wizardStep = 1;

    public string $wizardMappingBasis = '';

    /** @var list<array{basis_key: string, label: string, sales_account_no: string, vat_account_no: string, tips_account_no: string, cash_account_no: string, card_clearing_account_no: string, fees_account_no: string, rounding_account_no: string}> */
    public array $wizardMappingRows = [];

    /**
     * Shared ledger accounts for VAT-based setup (wizard step 3); copied onto each stored mapping row.
     *
     * @var array{vat_account_no: string, tips_account_no: string, cash_account_no: string, card_clearing_account_no: string, rounding_account_no: string}
     */
    public array $wizardSharedLedger = [
        'vat_account_no' => '',
        'tips_account_no' => '',
        'cash_account_no' => '',
        'card_clearing_account_no' => '',
        'rounding_account_no' => '',
    ];

    public function mount(): void
    {
        abort_unless(PowerOfficeIntegrationResource::canAccess(), 403);

        $store = Filament::getTenant();
        abort_unless($store, 403);

        $this->integration = PowerOfficeIntegration::query()->firstOrCreate(
            ['store_id' => $store->getKey()],
            [],
        );

        $this->wizardMappingBasis = $this->integration->mapping_basis->value;

        if ($this->shouldShowSettings()) {
            $this->fillSettingsForm();

            return;
        }

        if (! $this->integration->isConnected()) {
            $this->wizardStep = 1;

            return;
        }

        if ($this->integration->onboarding_completed_at === null) {
            $this->wizardStep = 2;

            return;
        }

        $this->wizardStep = 1;
    }

    public function getTitle(): string
    {
        return $this->shouldShowSettings() ? 'PowerOffice' : 'Set up PowerOffice';
    }

    /**
     * Full settings UI only after the Filament wizard is finished and PowerOffice is connected.
     */
    public function shouldShowSettings(): bool
    {
        return $this->integration !== null
            && $this->integration->onboarding_completed_at !== null
            && $this->integration->isConnected();
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->integration || ! $this->shouldShowSettings()) {
            return $schema->components([]);
        }

        return $schema
            ->model($this->integration)
            ->statePath('data')
            ->components([
                Section::make('Sync')
                    ->description('Turn off to stop all Z-report posting to PowerOffice (automatic and manual).')
                    ->schema([
                        Toggle::make('sync_enabled')
                            ->label('PowerOffice sync enabled')
                            ->default(true),
                        Toggle::make('auto_sync_on_z_report')
                            ->label('Sync automatically when a Z-report is generated')
                            ->default(true)
                            ->visible(fn (Get $get): bool => (bool) $get('sync_enabled')),
                    ]),
                Section::make('Connection')
                    ->description('You are signed in to PowerOffice Go for this store. Use “Connect / reconnect” in the header if you need to approve the integration again.')
                    ->schema([
                        Select::make('environment')
                            ->label('PowerOffice environment')
                            ->options(collect(PowerOfficeEnvironment::cases())->mapWithKeys(
                                fn (PowerOfficeEnvironment $e): array => [$e->value => $e->label()]
                            ))
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Accounting')
                    ->schema([
                        Select::make('mapping_basis')
                            ->label('How to split ledger lines')
                            ->options(collect(PowerOfficeMappingBasis::cases())->mapWithKeys(
                                fn (PowerOfficeMappingBasis $b): array => [$b->value => $b->label()]
                            ))
                            ->required()
                            ->live()
                            ->native(false),
                        Section::make('Revenue account by VAT rate')
                            ->description('Only rates you expect on Z-reports need a number. Each rate posts net sales to its own revenue account.')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(1)
                            ->schema(collect(PowerOfficeStandardVatRates::options())
                                ->map(fn (string $label, string $key): TextInput => TextInput::make('vat_sales_'.$key)
                                    ->label($label.' — sales / revenue account')
                                    ->maxLength(64))
                                ->values()
                                ->all()),
                        Section::make('VAT, tips, rounding, and payment fallbacks')
                            ->description('Output VAT, tips, and rounding always use these. Cash / card here are only used when **Ledger routing → Debit accounts per payment method** is empty or the Z-report has no `by_payment_method_net` (then net cash vs card uses these two accounts). PSP-style fees use **Ledger routing → Payment fees**, not a single field here.')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_shared_vat_account_no')
                                    ->label('VAT account (output VAT)')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_tips_account_no')
                                    ->label('Tips account')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_cash_account_no')
                                    ->label('Cash account (fallback)')
                                    ->helperText('Overridden by Ledger routing → cash when that field is set and the Z-report has per-method net amounts.')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_card_clearing_account_no')
                                    ->label('Card / clearing account (fallback)')
                                    ->helperText('Overridden by Ledger routing per-method accounts when set; also used for aggregated “electronic” net when not split by method.')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_rounding_account_no')
                                    ->label('Rounding account')
                                    ->maxLength(64),
                            ]),
                        Repeater::make('mappings')
                            ->label('Account numbers per line')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') !== PowerOfficeMappingBasis::Vat->value)
                            ->schema([
                                Select::make('basis_key')
                                    ->label('Line')
                                    ->options(function (Get $get): array {
                                        $raw = $get('../../mapping_basis');
                                        $basis = PowerOfficeMappingBasis::tryFrom((string) $raw)
                                            ?? $this->integration->mapping_basis;

                                        return $this->mappingLineOptions($basis);
                                    })
                                    ->required()
                                    ->searchable()
                                    ->native(false),
                                TextInput::make('basis_label')
                                    ->label('Description')
                                    ->maxLength(255),
                                TextInput::make('sales_account_no')
                                    ->label('Sales / revenue account')
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('vat_account_no')
                                    ->label('VAT account')
                                    ->maxLength(64),
                                TextInput::make('tips_account_no')
                                    ->label('Tips account')
                                    ->maxLength(64),
                                TextInput::make('cash_account_no')
                                    ->label('Cash account')
                                    ->maxLength(64),
                                TextInput::make('card_clearing_account_no')
                                    ->label('Card / clearing account')
                                    ->maxLength(64),
                                TextInput::make('fees_account_no')
                                    ->label('Fees account (optional)')
                                    ->helperText('Not used by Z-report PowerOffice sync. Configure PSP fees under Ledger routing → Payment fees.')
                                    ->maxLength(64),
                                TextInput::make('rounding_account_no')
                                    ->label('Rounding account')
                                    ->maxLength(64),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])
                            ->addActionLabel('Add line')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                Section::make('Ledger routing (payments & settlement)')
                    ->description('Optional PowerOffice account numbers for payment-type debits (like PSP scripts: cash, card terminal, Vipps, etc.), default revenue when a collection/vendor line is missing, gift-card liability, and paired fee/payout postings when the Z-report includes those amounts.')
                    ->schema([
                        TextInput::make('ledger_default_sales_account_no')
                            ->label('Default sales / revenue account (fallback)')
                            ->helperText('Used for product collection or vendor split when no mapping exists for that collection or vendor (e.g. newly added categories).')
                            ->visible(fn (Get $get): bool => in_array($get('mapping_basis'), [
                                PowerOfficeMappingBasis::Category->value,
                                PowerOfficeMappingBasis::Vendor->value,
                            ], true))
                            ->maxLength(64),
                        Section::make('Debit accounts per payment method (Z-report net)')
                            ->description('Matches POS charge payment_method values. Used when the Z-report includes by_payment_method_net (new sessions). Falls back to aggregated cash vs card if not present.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payment_debit_cash')
                                    ->label('cash')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card_present')
                                    ->label('card_present')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card')
                                    ->label('card')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_vipps')
                                    ->label('vipps')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_mobile')
                                    ->label('mobile')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_gift_token')
                                    ->label('gift_token (or other codes)')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_default')
                                    ->label('Default (any other method)')
                                    ->maxLength(64),
                            ]),
                        TextInput::make('ledger_giftcard_liability_account_no')
                            ->label('Gift card liability account')
                            ->helperText('Used when the Z-report includes gift_card_sales_minor.')
                            ->maxLength(64),
                        TextInput::make('ledger_interim_liquid_account_no')
                            ->label('Interim / PSP liquid account (reference)')
                            ->helperText('Optional note field: often the same account you use as the “credit” side for fees and payouts below (Stripe/Zettle balance before bank payout).')
                            ->maxLength(64),
                        Section::make('Payment fees (paired posting)')
                            ->description('If the Z-report includes stripe_fees_minor, posts credit to settlement account and debit to expense (same pattern as Zettle PAYMENT_FEE lines).')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_fee_credit_account_no')
                                    ->label('Fee settlement account (credit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_fee_debit_account_no')
                                    ->label('Fee expense account (debit)')
                                    ->maxLength(64),
                            ]),
                        Section::make('Payout to bank (paired posting)')
                            ->description('If the Z-report includes payout_to_bank_minor, posts credit from settlement and debit to bank.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payout_credit_account_no')
                                    ->label('Payout settlement account (credit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_payout_debit_bank_account_no')
                                    ->label('Bank account (debit)')
                                    ->maxLength(64),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    protected function fillSettingsForm(): void
    {
        $this->integration->load('accountMappings');

        $base = [
            'environment' => $this->integration->environment->value,
            'mapping_basis' => $this->integration->mapping_basis->value,
            'sync_enabled' => $this->integration->sync_enabled,
            'auto_sync_on_z_report' => $this->integration->auto_sync_on_z_report,
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

            $this->form->fill($base);

            return;
        }

        $base['mappings'] = $this->integration->accountMappings->map(fn (PowerOfficeAccountMapping $m): array => [
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

        $this->form->fill($base);
    }

    public function saveSettings(): void
    {
        abort_unless($this->shouldShowSettings(), 403);

        $data = $this->form->getState();

        $this->integration->update([
            'environment' => $data['environment'],
            'mapping_basis' => $data['mapping_basis'],
            'sync_enabled' => (bool) ($data['sync_enabled'] ?? true),
            'auto_sync_on_z_report' => (bool) ($data['auto_sync_on_z_report'] ?? true),
        ]);

        $this->integration->refresh();

        $this->saveMappingsForBasis($data);

        $this->persistLedgerSettingsFromForm($data);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
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

        return [
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
        ];
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

        $settings['ledger'] = $ledger;
        $integration->update(['settings' => $settings]);
        $this->integration = $integration;
    }

    /**
     * @param  Collection<int, PowerOfficeAccountMapping>  $mappings
     * @return array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}
     */
    protected function extractSharedAccountsFromMappings(Collection $mappings): array
    {
        $first = $mappings->first();
        if (! $first instanceof PowerOfficeAccountMapping) {
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

        PowerOfficeAccountMapping::query()
            ->where('power_office_integration_id', $integration->getKey())
            ->delete();

        foreach (PowerOfficeStandardVatRates::basisKeys() as $vatKey) {
            $sales = trim((string) ($data['vat_sales_'.$vatKey] ?? ''));
            if ($sales === '') {
                continue;
            }

            PowerOfficeAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'power_office_integration_id' => $integration->getKey(),
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

        PowerOfficeAccountMapping::query()
            ->where('power_office_integration_id', $integration->getKey())
            ->delete();

        foreach ($rows as $row) {
            if (empty($row['basis_key']) || empty($row['sales_account_no'])) {
                continue;
            }

            PowerOfficeAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'power_office_integration_id' => $integration->getKey(),
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
                ->label('Save')
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
            Action::make('refreshConnection')
                ->label('Refresh status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->shouldShowSettings())
                ->action(function (): void {
                    $this->integration?->refresh();
                    Notification::make()->title('Status updated')->success()->send();
                }),
            Action::make('startOnboarding')
                ->label('Connect / reconnect PowerOffice')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn (): bool => $this->shouldShowSettings())
                ->action(function (PowerOfficeOnboardingService $onboarding): void {
                    $this->runStartOnboarding($onboarding);
                }),
            Action::make('syncLatestZReport')
                ->label('Queue latest Z-report sync')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->shouldShowSettings() && $this->integration?->sync_enabled)
                ->action(function (): void {
                    $this->runSyncLatestZReport();
                }),
        ];
    }

    public function refreshIntegration(): void
    {
        $this->integration?->refresh();
    }

    public function wizardNext(): void
    {
        if ($this->wizardStep === 1) {
            $this->integration?->refresh();
            if (! $this->integration?->isConnected()) {
                Notification::make()
                    ->title('Connect PowerOffice first')
                    ->body('Use the button below to sign in to PowerOffice Go, then click Next.')
                    ->warning()
                    ->send();

                return;
            }
            $this->wizardStep = 2;

            return;
        }

        if ($this->wizardStep === 2) {
            if (PowerOfficeMappingBasis::tryFrom($this->wizardMappingBasis) === null) {
                Notification::make()
                    ->title('Choose how to split lines')
                    ->warning()
                    ->send();

                return;
            }

            $this->integration->update([
                'mapping_basis' => PowerOfficeMappingBasis::from($this->wizardMappingBasis),
            ]);
            $this->integration->refresh();
            $this->seedWizardMappingRows();
            $this->wizardStep = 3;

            return;
        }
    }

    public function wizardBack(): void
    {
        if ($this->wizardStep > 1) {
            $this->wizardStep--;
        }
    }

    public function completeWizard(): void
    {
        $isVatWizard = PowerOfficeMappingBasis::tryFrom($this->wizardMappingBasis) === PowerOfficeMappingBasis::Vat;

        if ($isVatWizard) {
            $hasAnySales = collect($this->wizardMappingRows)->contains(
                fn (array $row): bool => trim((string) ($row['sales_account_no'] ?? '')) !== '',
            );
            if (! $hasAnySales) {
                Notification::make()
                    ->title('Sales account required')
                    ->body('Enter at least one sales/revenue account for the VAT rates you use.')
                    ->warning()
                    ->send();

                return;
            }
        } else {
            foreach ($this->wizardMappingRows as $row) {
                if (trim((string) ($row['sales_account_no'] ?? '')) === '') {
                    Notification::make()
                        ->title('Sales account required')
                        ->body('Enter a sales/revenue account for each row (you can use the same number for all).')
                        ->warning()
                        ->send();

                    return;
                }
            }
        }

        $this->integration->update([
            'mapping_basis' => PowerOfficeMappingBasis::from($this->wizardMappingBasis),
            'onboarding_completed_at' => now(),
        ]);
        $this->integration->refresh();

        $isVat = PowerOfficeMappingBasis::from($this->wizardMappingBasis) === PowerOfficeMappingBasis::Vat;
        $shared = $this->wizardSharedLedger;

        $rows = [];
        foreach ($this->wizardMappingRows as $row) {
            if ($isVat && trim((string) ($row['sales_account_no'] ?? '')) === '') {
                continue;
            }

            $rows[] = [
                'basis_key' => $row['basis_key'],
                'basis_label' => $row['label'] ?? null,
                'sales_account_no' => $row['sales_account_no'],
                'vat_account_no' => $isVat
                    ? (trim((string) ($shared['vat_account_no'] ?? '')) !== '' ? $shared['vat_account_no'] : null)
                    : ($row['vat_account_no'] ?: null),
                'tips_account_no' => $isVat
                    ? (trim((string) ($shared['tips_account_no'] ?? '')) !== '' ? $shared['tips_account_no'] : null)
                    : ($row['tips_account_no'] ?: null),
                'cash_account_no' => $isVat
                    ? (trim((string) ($shared['cash_account_no'] ?? '')) !== '' ? $shared['cash_account_no'] : null)
                    : ($row['cash_account_no'] ?: null),
                'card_clearing_account_no' => $isVat
                    ? (trim((string) ($shared['card_clearing_account_no'] ?? '')) !== '' ? $shared['card_clearing_account_no'] : null)
                    : ($row['card_clearing_account_no'] ?: null),
                'fees_account_no' => $isVat ? null : ($row['fees_account_no'] ?: null),
                'rounding_account_no' => $isVat
                    ? (trim((string) ($shared['rounding_account_no'] ?? '')) !== '' ? $shared['rounding_account_no'] : null)
                    : ($row['rounding_account_no'] ?: null),
                'is_active' => true,
            ];
        }

        $this->saveMappingsFromRepeater($rows);

        Notification::make()
            ->title('Setup complete')
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: true);
    }

    public function startOnboardingWizard(): void
    {
        $this->runStartOnboarding(app(PowerOfficeOnboardingService::class));
    }

    protected function runStartOnboarding(PowerOfficeOnboardingService $onboarding): void
    {
        $store = Filament::getTenant();
        if (! $store || ! $this->integration) {
            return;
        }

        try {
            $url = $onboarding->initiate($store, $this->integration);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Onboarding failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Continue in PowerOffice')
            ->body('Complete activation in the new window, then return here and refresh or click Next.')
            ->success()
            ->send();

        $this->js('window.open('.json_encode($url).", '_blank')");
    }

    protected function runSyncLatestZReport(): void
    {
        $store = Filament::getTenant();
        if (! $store) {
            return;
        }

        $session = PosSession::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->first();

        if (! $session) {
            Notification::make()
                ->title('No closed sessions')
                ->warning()
                ->send();

            return;
        }

        SyncPowerOfficeZReportJob::dispatch($session->id, true);

        Notification::make()
            ->title('Sync queued')
            ->body('Session '.$session->session_number)
            ->success()
            ->send();
    }

    protected function seedWizardMappingRows(): void
    {
        $options = $this->mappingLineOptions($this->integration->mapping_basis);
        $this->wizardMappingRows = collect($options)
            ->map(fn (string $label, string|int $key): array => [
                'basis_key' => (string) $key,
                'label' => $label,
                'sales_account_no' => '',
                'vat_account_no' => '',
                'tips_account_no' => '',
                'cash_account_no' => '',
                'card_clearing_account_no' => '',
                'fees_account_no' => '',
                'rounding_account_no' => '',
            ])
            ->values()
            ->all();

        $this->wizardSharedLedger = [
            'vat_account_no' => '',
            'tips_account_no' => '',
            'cash_account_no' => '',
            'card_clearing_account_no' => '',
            'rounding_account_no' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mappingLineOptions(PowerOfficeMappingBasis $basis): array
    {
        $integration = $this->integration;
        if (! $integration) {
            return [];
        }

        return match ($basis) {
            PowerOfficeMappingBasis::Vat => PowerOfficeStandardVatRates::options(),
            PowerOfficeMappingBasis::Category => ProductCollection::query()
                ->where('store_id', $integration->store_id)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (ProductCollection $c): array => [(string) $c->getKey() => $c->name])
                ->all() + ['0' => 'Uncategorized'],
            PowerOfficeMappingBasis::Vendor => Vendor::query()
                ->where('store_id', $integration->store_id)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Vendor $v): array => [(string) $v->getKey() => $v->name])
                ->all() + ['no-vendor' => 'Ingen leverandør'],
            PowerOfficeMappingBasis::PaymentMethod => [
                'cash' => 'Cash',
                'card' => 'Card',
                'card_present' => 'Card (terminal)',
                'vipps' => 'Vipps',
                'mobile' => 'Mobile',
            ],
        };
    }

    public function recentSyncRuns(): Collection
    {
        if (! $this->integration) {
            return collect();
        }

        return PowerOfficeSyncRun::query()
            ->where('power_office_integration_id', $this->integration->getKey())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }
}
