<?php

namespace App\Filament\Resources\PowerOfficeIntegrations\Pages;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Concerns\BuildsClusterWideSubNavigation;
use App\Filament\Resources\ArticleGroupCodes\ArticleGroupCodeResource;
use App\Filament\Resources\PowerOfficeIntegrations\PowerOfficeIntegrationResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Jobs\SyncPowerOfficeZReportJob;
use App\Models\ArticleGroupCode;
use App\Models\Collection as ProductCollection;
use App\Models\ConnectedProduct;
use App\Models\PosSession;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\PowerOfficeSyncRun;
use App\Models\Vendor;
use App\Services\PowerOffice\PowerOfficeAccountStatusService;
use App\Services\PowerOffice\PowerOfficeOnboardingService;
use App\Support\PowerOffice\PowerOfficeLedgerDefaults;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
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
    use BuildsClusterWideSubNavigation;
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $resource = PowerOfficeIntegrationResource::class;

    protected string $view = 'filament.resources.power-office-integrations.pages.manage-power-office-integration';

    public ?PowerOfficeIntegration $integration = null;

    public ?array $data = [];

    /**
     * Result of the last "check accounts in PowerOffice" run (see PowerOfficeAccountStatusService::check()).
     */
    public ?array $powerOfficeAccountStatus = null;

    public function mount(): void
    {
        abort_unless(PowerOfficeIntegrationResource::canAccess(), 403);

        $store = Filament::getTenant();
        abort_unless($store, 403);

        $this->integration = PowerOfficeIntegration::query()->firstOrCreate(
            ['store_id' => $store->getKey()],
            [
                'mapping_basis' => PowerOfficeLedgerDefaults::mappingBasis(),
                'settings' => [
                    'ledger' => PowerOfficeLedgerDefaults::ledgerSettings(),
                ],
            ],
        );

        if ($this->shouldShowSettings()) {
            $this->markOnboardingCompleteIfConnected();
            $this->fillSettingsForm();
        }
    }

    public function getTitle(): string
    {
        return $this->shouldShowSettings() ? 'PowerOffice' : 'Set up PowerOffice';
    }

    public function getSubNavigation(): array
    {
        return $this->clusterWideSubNavigationMergedWith([]);
    }

    /**
     * Full settings UI once PowerOffice is connected; ledger configuration lives here only.
     */
    public function shouldShowSettings(): bool
    {
        return $this->integration !== null
            && $this->integration->isConnected();
    }

    protected function markOnboardingCompleteIfConnected(): void
    {
        if ($this->integration?->onboarding_completed_at !== null) {
            return;
        }

        $this->integration->update(['onboarding_completed_at' => now()]);
        $this->integration->refresh();
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
                    ->description(__('Turn off to stop all Z-report posting to PowerOffice (automatic and manual).'))
                    ->schema([
                        Toggle::make('sync_enabled')
                            ->label(__('PowerOffice sync enabled'))
                            ->default(true),
                        Toggle::make('auto_sync_on_z_report')
                            ->label(__('Sync automatically when a Z-report is generated'))
                            ->default(true)
                            ->visible(fn (Get $get): bool => (bool) $get('sync_enabled')),
                    ]),
                Section::make('Connection')
                    ->description(__('You are signed in to PowerOffice Go for this store. Use “Connect / reconnect” in the header if you need to approve the integration again.'))
                    ->schema([
                        Select::make('environment')
                            ->label(__('PowerOffice environment'))
                            ->options(collect(PowerOfficeEnvironment::cases())->mapWithKeys(
                                fn (PowerOfficeEnvironment $e): array => [$e->value => $e->label()]
                            ))
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Accounting')
                    ->schema([
                        Select::make('mapping_basis')
                            ->label(__('How to split ledger lines'))
                            ->options(collect(PowerOfficeMappingBasis::integrationMappingBases())->mapWithKeys(
                                fn (PowerOfficeMappingBasis $b): array => [$b->value => $b->label()]
                            ))
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Get $get): ?string => match ($get('mapping_basis')) {
                                PowerOfficeMappingBasis::Category->value => __('Hybrid mode: article group code is the primary income account; product collection is fallback. Vendors with commission % (e.g. Stuttreist) split automatically from the Vendors screen.'),
                                PowerOfficeMappingBasis::Vendor->value => __('Not recommended for Jobberiet-style setups: all turnover posts to vendor reskontro instead of varegruppe accounts.'),
                                PowerOfficeMappingBasis::PaymentMethod->value => __('Revenue account per payment method. VAT, tips, and payment debits use the shared setup below.'),
                                default => null,
                            }),
                        Section::make('Revenue account by VAT rate')
                            ->description(__('Only rates you expect on Z-reports need a number. Each rate posts net sales to its own revenue account.'))
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(1)
                            ->schema(collect(PowerOfficeStandardVatRates::options())
                                ->map(fn (string $label, string $key): TextInput => TextInput::make('vat_sales_'.$key)
                                    ->label($label.' — sales / revenue account')
                                    ->maxLength(64))
                                ->values()
                                ->all()),
                        Section::make('Vendor revenue accounts')
                            ->description(__('Per-vendor reskontro and commission are edited on each vendor. Missing reskontro falls back to the default sales account in Ledger routing.'))
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vendor->value)
                            ->schema([
                                ViewField::make('vendor_ledger_overview')
                                    ->view('filament.resources.power-office-integrations.components.vendor-ledger-overview')
                                    ->viewData(fn (): array => [
                                        'vendors' => $this->storeVendorsForLedgerOverview(),
                                        'vendorsUrl' => $this->vendorsIndexUrl(),
                                    ]),
                            ]),
                        Section::make('VAT, tips, rounding, and payment fallbacks')
                            ->description(__('Output VAT, tips, and rounding always use these. Cash / card here are only used when **Ledger routing → Debit accounts per payment method** is empty or the Z-report has no `by_payment_method_net` (then net cash vs card uses these two accounts). PSP-style fees use **Ledger routing → Payment fees**, not a single field here.'))
                            ->visible(fn (Get $get): bool => $this->usesSharedLedgerAccountsSection($get('mapping_basis')))
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_shared_vat_account_no')
                                    ->label(__('VAT account (output VAT)'))
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_tips_account_no')
                                    ->label(__('Tips account'))
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_cash_account_no')
                                    ->label(__('Cash account (fallback)'))
                                    ->helperText(__('Overridden by Ledger routing → cash when that field is set and the Z-report has per-method net amounts.'))
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_card_clearing_account_no')
                                    ->label(__('Card / clearing account (fallback)'))
                                    ->helperText(__('Overridden by Ledger routing per-method accounts when set; also used for aggregated “electronic” net when not split by method.'))
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_rounding_account_no')
                                    ->label(__('Rounding account'))
                                    ->maxLength(64),
                            ]),
                        Section::make('Revenue account per article group code')
                            ->description(__('Primary income routing: each product’s article group code decides the sales account. Momskode (3 vs 0) is set on the GL account in PowerOffice Go; use 0% VAT on Kantine products in POS.'))
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Category->value)
                            ->schema([
                                Repeater::make('article_group_mappings')
                                    ->label(__('Article group → sales account'))
                                    ->schema([
                                        Select::make('basis_key')
                                            ->label(__('Article group code'))
                                            ->options(fn (): array => $this->articleGroupLineOptions())
                                            ->required()
                                            ->searchable()
                                            ->native(false),
                                        TextInput::make('sales_account_no')
                                            ->label(__('Sales / revenue account'))
                                            ->required()
                                            ->maxLength(64),
                                        Toggle::make('is_active')
                                            ->label(__('Active'))
                                            ->default(true),
                                    ])
                                    ->addActionLabel(__('Add article group'))
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                ViewField::make('article_group_setup_status')
                                    ->view('filament.resources.power-office-integrations.components.article-group-setup-status')
                                    ->viewData(fn (): array => [
                                        'rows' => $this->articleGroupSetupRows(),
                                        'articleGroupCodesUrl' => $this->articleGroupCodesIndexUrl(),
                                    ]),
                            ]),
                        Repeater::make('mappings')
                            ->label(fn (Get $get): string => match ($get('mapping_basis')) {
                                PowerOfficeMappingBasis::Category->value => __('Revenue account per product collection (fallback)'),
                                PowerOfficeMappingBasis::PaymentMethod->value => __('Revenue account per payment method'),
                                default => __('Account numbers per line'),
                            })
                            ->visible(fn (Get $get): bool => $this->usesMappingRepeater($get('mapping_basis')))
                            ->schema([
                                Select::make('basis_key')
                                    ->label(__('Line'))
                                    ->options(function (Get $get): array {
                                        $raw = $get('../../mapping_basis');
                                        $basis = PowerOfficeMappingBasis::tryFrom((string) $raw)
                                            ?? $this->integration->mapping_basis;

                                        return $this->mappingLineOptions($basis);
                                    })
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => $get('../../mapping_basis') === PowerOfficeMappingBasis::Category->value)
                                    ->dehydrated(),
                                TextInput::make('sales_account_no')
                                    ->label(__('Sales / revenue account'))
                                    ->required()
                                    ->maxLength(64),
                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->default(true),
                            ])
                            ->addActionLabel(__('Add line'))
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                Section::make('Ledger routing (payments & settlement)')
                    ->description(__('Optional PowerOffice account numbers for payment-type debits (like PSP scripts: cash, card terminal, Vipps, etc.), default revenue when a collection/vendor line is missing, gift-card liability, and paired fee/payout postings when the Z-report includes those amounts.'))
                    ->schema([
                        TextInput::make('ledger_department_no')
                            ->label(__('Department number (all turnover)'))
                            ->helperText(__('Applied to sales, VAT, and tips lines on each Z-report voucher (e.g. 20).'))
                            ->maxLength(16),
                        TextInput::make('ledger_commission_revenue_account_no')
                            ->label(__('Default commission / Jobberiet revenue account'))
                            ->helperText(__('Fallback when a vendor has commission but no per-vendor commission account. Vendor reskontro is set on each vendor.'))
                            ->maxLength(64),
                        TextInput::make('ledger_default_sales_account_no')
                            ->label(__('Default sales / revenue account (fallback)'))
                            ->helperText(__('Used when no article group or collection mapping exists for a product (e.g. newly added groups).'))
                            ->visible(fn (Get $get): bool => in_array($get('mapping_basis'), [
                                PowerOfficeMappingBasis::Category->value,
                                PowerOfficeMappingBasis::Vendor->value,
                            ], true))
                            ->maxLength(64),
                        Section::make('Debit accounts per payment method (Z-report net)')
                            ->description(__('Matches POS charge payment_method values. Used when the Z-report includes by_payment_method_net (new sessions). Falls back to aggregated cash vs card if not present.'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payment_debit_cash')
                                    ->label(__('cash'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card_present')
                                    ->label(__('card_present'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card')
                                    ->label(__('card'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_vipps')
                                    ->label(__('vipps'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_mobile')
                                    ->label(__('mobile'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_gift_token')
                                    ->label(__('gift_token (or other codes)'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_default')
                                    ->label(__('Default (any other method)'))
                                    ->maxLength(64),
                            ]),
                        TextInput::make('ledger_giftcard_liability_account_no')
                            ->label(__('Gift card liability account'))
                            ->helperText(__('Used when the Z-report includes gift_card_sales_minor.'))
                            ->maxLength(64),
                        TextInput::make('ledger_interim_liquid_account_no')
                            ->label(__('Interim / PSP liquid account (reference)'))
                            ->helperText(__('Optional note field: often the same account you use as the “credit” side for fees and payouts below (Stripe/Zettle balance before bank payout).'))
                            ->maxLength(64),
                        Section::make('Payment fees (paired posting)')
                            ->description(__('If the Z-report includes stripe_fees_minor, posts credit to settlement account and debit to expense (same pattern as Zettle PAYMENT_FEE lines).'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_fee_credit_account_no')
                                    ->label(__('Fee settlement account (credit)'))
                                    ->maxLength(64),
                                TextInput::make('ledger_fee_debit_account_no')
                                    ->label(__('Fee expense account (debit)'))
                                    ->maxLength(64),
                            ]),
                        TextInput::make('ledger_vipps_fee_debit_account_no')
                            ->label(__('Vipps fee expense account'))
                            ->helperText(__('Debit Vipps application fees separately so the Vipps clearing account matches bank deposits (e.g. 7720).'))
                            ->maxLength(64),
                        Section::make('Payout to bank (paired posting)')
                            ->description(__('If the Z-report includes payout_to_bank_minor, posts credit from settlement and debit to bank.'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payout_credit_account_no')
                                    ->label(__('Payout settlement account (credit)'))
                                    ->maxLength(64),
                                TextInput::make('ledger_payout_debit_bank_account_no')
                                    ->label(__('Bank account (debit)'))
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
                    ?->sales_account_no ?? PowerOfficeLedgerDefaults::vatRateSalesAccounts()[$vatKey] ?? '';
            }
            $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults(
                $this->sharedAccountsToLedgerFormKeys($shared)
            ));
            $base['mappings'] = [];
            $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults($this->ledgerFormStateFromIntegration()));

            $this->form->fill($base);

            return;
        }

        if ($this->integration->mapping_basis === PowerOfficeMappingBasis::Vendor) {
            $shared = $this->extractSharedAccountsFromMappings($this->integration->accountMappings);
            $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults(
                $this->sharedAccountsToLedgerFormKeys($shared)
            ));
            $base['mappings'] = [];
            $base['article_group_mappings'] = [];
            $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults($this->ledgerFormStateFromIntegration()));

            $this->form->fill($base);

            return;
        }

        if ($this->integration->mapping_basis === PowerOfficeMappingBasis::Category) {
            $base = array_merge($base, $this->fillHybridCategoryMappingFormState());

            $this->form->fill($base);

            return;
        }

        $lineOptions = $this->mappingLineOptions($this->integration->mapping_basis);

        $base['mappings'] = $this->integration->accountMappings
            ->reject(fn (PowerOfficeAccountMapping $m): bool => $m->basis_key === PowerOfficeLedgerSettings::SHARED_MAPPING_BASIS_KEY)
            ->map(fn (PowerOfficeAccountMapping $m): array => [
                'basis_key' => $m->basis_key,
                'sales_account_no' => $m->sales_account_no ?? '',
                'is_active' => $m->is_active,
            ])->values()->all();

        $base['article_group_mappings'] = [];

        if ($this->integration->mapping_basis === PowerOfficeMappingBasis::PaymentMethod) {
            $shared = $this->extractSharedAccountsFromMappings($this->integration->accountMappings);
            $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults(
                $this->sharedAccountsToLedgerFormKeys($shared)
            ));
        }

        $base = array_merge($base, PowerOfficeLedgerDefaults::mergeFormDefaults($this->ledgerFormStateFromIntegration()));

        $this->form->fill($base);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fillHybridCategoryMappingFormState(): array
    {
        $shared = $this->extractSharedAccountsFromMappings($this->integration->accountMappings);
        $articleOptions = $this->articleGroupLineOptions();
        $collectionOptions = $this->mappingLineOptions(PowerOfficeMappingBasis::Category);

        $articleGroupMappings = $this->integration->accountMappings
            ->where('basis_type', PowerOfficeMappingBasis::ArticleGroup)
            ->map(function (PowerOfficeAccountMapping $m) use ($articleOptions): array {
                $salesAccount = $m->sales_account_no;
                if (! filled($salesAccount)) {
                    $label = $articleOptions[(string) $m->basis_key] ?? '';
                    $name = str_contains($label, ' — ') ? trim(explode(' — ', $label, 2)[1]) : $label;
                    $salesAccount = PowerOfficeLedgerDefaults::salesAccountForArticleGroupName($name);
                }

                return [
                    'basis_key' => $m->basis_key,
                    'sales_account_no' => $salesAccount ?? '',
                    'is_active' => $m->is_active,
                ];
            })->values()->all();

        if ($articleGroupMappings === []) {
            $articleGroupMappings = collect($articleOptions)
                ->map(fn (string $label, string $code): array => [
                    'basis_key' => $code,
                    'sales_account_no' => PowerOfficeLedgerDefaults::salesAccountForArticleGroupName(
                        str_contains($label, ' — ') ? trim(explode(' — ', $label, 2)[1]) : $label
                    ) ?? '',
                    'is_active' => true,
                ])
                ->filter(fn (array $row): bool => filled($row['sales_account_no']))
                ->values()
                ->all();
        }

        $collectionMappings = $this->integration->accountMappings
            ->where('basis_type', PowerOfficeMappingBasis::Category)
            ->map(function (PowerOfficeAccountMapping $m): array {
                $salesAccount = $m->sales_account_no;
                if (! filled($salesAccount)) {
                    if ($m->basis_key === '0') {
                        $salesAccount = PowerOfficeLedgerDefaults::ledgerSettings()['default_sales_account_no'] ?? null;
                    } elseif (is_numeric($m->basis_key)) {
                        $collection = ProductCollection::query()->find((int) $m->basis_key);
                        $salesAccount = $collection
                            ? PowerOfficeLedgerDefaults::salesAccountForCollectionName($collection->name)
                            : null;
                    }
                }

                return [
                    'basis_key' => $m->basis_key,
                    'sales_account_no' => $salesAccount ?? '',
                    'is_active' => $m->is_active,
                ];
            })->values()->all();

        if ($collectionMappings === []) {
            $collectionMappings = collect($collectionOptions)
                ->map(fn (string $label, string|int $key): array => [
                    'basis_key' => (string) $key,
                    'sales_account_no' => $this->defaultWizardSalesAccountForLine($label, (string) $key),
                    'is_active' => true,
                ])
                ->values()
                ->all();
        }

        return array_merge(
            PowerOfficeLedgerDefaults::mergeFormDefaults($this->sharedAccountsToLedgerFormKeys($shared)),
            PowerOfficeLedgerDefaults::mergeFormDefaults($this->ledgerFormStateFromIntegration()),
            [
                'article_group_mappings' => $articleGroupMappings,
                'mappings' => $collectionMappings,
            ],
        );
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
            ->title(__('Saved'))
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
        $pmf = is_array($l['payment_method_fees'] ?? null) ? $l['payment_method_fees'] : [];
        $vippsFee = is_array($pmf['vipps'] ?? null) ? $pmf['vipps'] : [];

        return [
            'ledger_department_no' => (string) ($l['department_no'] ?? ''),
            'ledger_commission_revenue_account_no' => (string) ($l['commission_revenue_account_no'] ?? ''),
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
            'ledger_vipps_fee_debit_account_no' => (string) ($vippsFee['debit_account_no'] ?? ''),
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

        $departmentNo = trim((string) ($data['ledger_department_no'] ?? ''));
        if ($departmentNo !== '') {
            $ledger['department_no'] = $departmentNo;
        }

        $commissionAccount = trim((string) ($data['ledger_commission_revenue_account_no'] ?? ''));
        if ($commissionAccount !== '') {
            $ledger['commission_revenue_account_no'] = $commissionAccount;
        }

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

        $vippsFeeDebit = trim((string) ($data['ledger_vipps_fee_debit_account_no'] ?? ''));
        if ($vippsFeeDebit !== '') {
            $ledger['payment_method_fees'] = [
                'vipps' => [
                    'debit_account_no' => $vippsFeeDebit,
                ],
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
        $first = $mappings->firstWhere('basis_key', PowerOfficeLedgerSettings::SHARED_MAPPING_BASIS_KEY)
            ?? $mappings->first();
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

        if ($basis === PowerOfficeMappingBasis::Vendor) {
            $this->saveVendorBasisSharedMapping($data);

            return;
        }

        if ($basis === PowerOfficeMappingBasis::Category) {
            $this->saveHybridCategoryMappings($data, $this->ledgerSharedAccountsFromFormData($data));

            return;
        }

        $shared = null;
        if ($basis === PowerOfficeMappingBasis::PaymentMethod) {
            $shared = $this->ledgerSharedAccountsFromFormData($data);
        }

        $this->saveMappingsFromRepeater($data['mappings'] ?? [], $shared, $basis);
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
     * @param  array<string, mixed>  $data
     * @param  array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}  $shared
     */
    protected function saveHybridCategoryMappings(array $data, array $shared): void
    {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        PowerOfficeAccountMapping::query()
            ->where('power_office_integration_id', $integration->getKey())
            ->whereIn('basis_type', [
                PowerOfficeMappingBasis::Category,
                PowerOfficeMappingBasis::ArticleGroup,
            ])
            ->delete();

        $articleOptions = $this->articleGroupLineOptions();
        foreach ($data['article_group_mappings'] ?? [] as $row) {
            if (empty($row['basis_key']) || empty($row['sales_account_no'])) {
                continue;
            }

            PowerOfficeAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'power_office_integration_id' => $integration->getKey(),
                'basis_type' => PowerOfficeMappingBasis::ArticleGroup,
                'basis_key' => (string) $row['basis_key'],
                'basis_label' => $articleOptions[(string) $row['basis_key']] ?? null,
                'sales_account_no' => (string) $row['sales_account_no'],
                'vat_account_no' => $shared['vat_account_no'],
                'tips_account_no' => $shared['tips_account_no'],
                'cash_account_no' => $shared['cash_account_no'],
                'card_clearing_account_no' => $shared['card_clearing_account_no'],
                'fees_account_no' => null,
                'rounding_account_no' => $shared['rounding_account_no'],
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
        }

        $collectionOptions = $this->mappingLineOptions(PowerOfficeMappingBasis::Category);
        foreach ($data['mappings'] ?? [] as $row) {
            if (empty($row['basis_key']) || empty($row['sales_account_no'])) {
                continue;
            }

            PowerOfficeAccountMapping::query()->create([
                'store_id' => $integration->store_id,
                'power_office_integration_id' => $integration->getKey(),
                'basis_type' => PowerOfficeMappingBasis::Category,
                'basis_key' => (string) $row['basis_key'],
                'basis_label' => $collectionOptions[(string) $row['basis_key']] ?? null,
                'sales_account_no' => (string) $row['sales_account_no'],
                'vat_account_no' => $shared['vat_account_no'],
                'tips_account_no' => $shared['tips_account_no'],
                'cash_account_no' => $shared['cash_account_no'],
                'card_clearing_account_no' => $shared['card_clearing_account_no'],
                'fees_account_no' => null,
                'rounding_account_no' => $shared['rounding_account_no'],
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{vat_account_no: ?string, tips_account_no: ?string, cash_account_no: ?string, card_clearing_account_no: ?string, rounding_account_no: ?string}|null  $shared
     */
    protected function saveMappingsFromRepeater(
        array $rows,
        ?array $shared = null,
        ?PowerOfficeMappingBasis $basis = null,
    ): void {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        $mappingBasis = $basis ?? $integration->mapping_basis;
        $lineOptions = $this->mappingLineOptions($mappingBasis);

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
                'basis_type' => $mappingBasis,
                'basis_key' => (string) $row['basis_key'],
                'basis_label' => $lineOptions[(string) $row['basis_key']] ?? null,
                'sales_account_no' => (string) $row['sales_account_no'],
                'vat_account_no' => $shared['vat_account_no'] ?? null,
                'tips_account_no' => $shared['tips_account_no'] ?? null,
                'cash_account_no' => $shared['cash_account_no'] ?? null,
                'card_clearing_account_no' => $shared['card_clearing_account_no'] ?? null,
                'fees_account_no' => null,
                'rounding_account_no' => $shared['rounding_account_no'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveVendorBasisSharedMapping(array $data): void
    {
        $integration = $this->integration->fresh();
        abort_unless($integration, 404);

        $shared = $this->ledgerSharedAccountsFromFormData($data);
        $defaultSales = trim((string) ($data['ledger_default_sales_account_no'] ?? ''));
        if ($defaultSales === '') {
            $defaultSales = (string) (PowerOfficeLedgerDefaults::ledgerSettings()['default_sales_account_no'] ?? '3000');
        }

        PowerOfficeAccountMapping::query()
            ->where('power_office_integration_id', $integration->getKey())
            ->delete();

        PowerOfficeAccountMapping::query()->create([
            'store_id' => $integration->store_id,
            'power_office_integration_id' => $integration->getKey(),
            'basis_type' => PowerOfficeMappingBasis::Vendor,
            'basis_key' => PowerOfficeLedgerSettings::SHARED_MAPPING_BASIS_KEY,
            'basis_label' => __('Shared ledger accounts'),
            'sales_account_no' => $defaultSales,
            'vat_account_no' => $shared['vat_account_no'],
            'tips_account_no' => $shared['tips_account_no'],
            'cash_account_no' => $shared['cash_account_no'],
            'card_clearing_account_no' => $shared['card_clearing_account_no'],
            'fees_account_no' => null,
            'rounding_account_no' => $shared['rounding_account_no'],
            'is_active' => true,
        ]);
    }

    protected function usesSharedLedgerAccountsSection(?string $mappingBasis): bool
    {
        return in_array($mappingBasis, [
            PowerOfficeMappingBasis::Vat->value,
            PowerOfficeMappingBasis::Category->value,
            PowerOfficeMappingBasis::Vendor->value,
            PowerOfficeMappingBasis::PaymentMethod->value,
        ], true);
    }

    protected function usesMappingRepeater(?string $mappingBasis): bool
    {
        return in_array($mappingBasis, [
            PowerOfficeMappingBasis::Category->value,
            PowerOfficeMappingBasis::PaymentMethod->value,
        ], true);
    }

    /**
     * @return Collection<int, Vendor>
     */
    public function storeVendorsForLedgerOverview(): Collection
    {
        $store = Filament::getTenant();
        if (! $store) {
            return collect();
        }

        return Vendor::query()
            ->where('store_id', $store->getKey())
            ->orderBy('name')
            ->get();
    }

    protected function vendorsIndexUrl(): ?string
    {
        $store = Filament::getTenant();
        if (! $store) {
            return null;
        }

        return VendorResource::getUrl('index', ['tenant' => $store]);
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
            Action::make('refreshConnection')
                ->label(__('Refresh status'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->shouldShowSettings())
                ->action(function (): void {
                    $this->integration?->refresh();
                    Notification::make()->title(__('Status updated'))->success()->send();
                }),
            Action::make('startOnboarding')
                ->label(__('Connect / reconnect PowerOffice'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn (): bool => $this->shouldShowSettings())
                ->action(function (PowerOfficeOnboardingService $onboarding): void {
                    $this->runStartOnboarding($onboarding);
                }),
            Action::make('syncLatestZReport')
                ->label(__('Queue latest Z-report sync'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->shouldShowSettings() && $this->integration?->sync_enabled)
                ->action(function (): void {
                    $this->runSyncLatestZReport();
                }),
        ];
    }

    public function checkPowerOfficeAccountsAction(): Action
    {
        return Action::make('checkPowerOfficeAccounts')
            ->label(__('Check accounts in PowerOffice'))
            ->icon('heroicon-o-magnifying-glass')
            ->color('gray')
            ->action(function (PowerOfficeAccountStatusService $service): void {
                $integration = $this->integration?->fresh('accountMappings');
                if (! $integration) {
                    return;
                }

                try {
                    $this->powerOfficeAccountStatus = $service->check($integration);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title(__('PowerOffice account check failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $missing = $this->missingPowerOfficeAccountCount();

                $notification = Notification::make()
                    ->title($missing === 0
                        ? __('All account numbers exist in PowerOffice')
                        : __(':count account number(s) are missing in PowerOffice', ['count' => $missing]));
                $missing === 0 ? $notification->success() : $notification->warning();
                $notification->send();
            });
    }

    public function createMissingPowerOfficeAccountsAction(): Action
    {
        return Action::make('createMissingPowerOfficeAccounts')
            ->label(__('Create missing in PowerOffice'))
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->visible(fn (): bool => $this->missingPowerOfficeAccountCount() > 0)
            ->modalHeading(__('Create missing PowerOffice accounts'))
            ->modalDescription(__('GL accounts are created via the Accounting Settings API (vat code is set on the account); vendor reskontro numbers are created as suppliers. Remove rows you do not want to create.'))
            ->fillForm(fn (): array => [
                'gl_accounts' => collect($this->powerOfficeAccountStatus['gl'] ?? [])
                    ->reject(fn (array $row): bool => $row['exists'])
                    ->map(fn (array $row): array => [
                        'account_no' => $row['account_no'],
                        'name' => implode(', ', $row['purposes']),
                        'vat_code' => $row['suggested_vat_code'],
                    ])
                    ->values()
                    ->all(),
                'suppliers' => collect($this->powerOfficeAccountStatus['suppliers'] ?? [])
                    ->reject(fn (array $row): bool => $row['exists'])
                    ->map(fn (array $row): array => [
                        'number' => $row['number'],
                        'name' => $row['vendor'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->form([
                Repeater::make('gl_accounts')
                    ->label(__('General ledger accounts'))
                    ->addable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->columns(3)
                    ->schema([
                        TextInput::make('account_no')
                            ->label(__('Account'))
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(120),
                        Select::make('vat_code')
                            ->label(__('VAT code'))
                            ->options([
                                '0' => __('0 — No VAT'),
                                '3' => __('3 — Outgoing VAT 25%'),
                                '31' => __('31 — Outgoing VAT 15%'),
                                '33' => __('33 — Outgoing VAT low rate'),
                                '5' => __('5 — Exempt domestic turnover'),
                                '6' => __('6 — Outside the VAT act'),
                            ])
                            ->required()
                            ->native(false),
                    ]),
                Repeater::make('suppliers')
                    ->label(__('Vendor reskontro (suppliers)'))
                    ->addable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label(__('Reskontro number'))
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label(__('Supplier name'))
                            ->required()
                            ->maxLength(120),
                    ]),
            ])
            ->action(function (array $data, PowerOfficeAccountStatusService $service): void {
                $integration = $this->integration?->fresh();
                if (! $integration) {
                    return;
                }

                $created = 0;
                $errors = [];

                foreach ($data['gl_accounts'] ?? [] as $row) {
                    $result = $service->createGlAccount(
                        $integration,
                        (string) $row['account_no'],
                        (string) $row['name'],
                        (string) $row['vat_code'],
                    );
                    $result['ok'] ? $created++ : $errors[] = $row['account_no'].': '.$result['error'];
                }

                foreach ($data['suppliers'] ?? [] as $row) {
                    $result = $service->createSupplier($integration, (string) $row['number'], (string) $row['name']);
                    $result['ok'] ? $created++ : $errors[] = $row['number'].': '.$result['error'];
                }

                try {
                    $this->powerOfficeAccountStatus = $service->check($integration->fresh('accountMappings'));
                } catch (\Throwable) {
                    // Keep the previous status if the refresh fails; the notification below still reports results.
                }

                if ($errors === []) {
                    Notification::make()
                        ->title(__(':count account(s) created in PowerOffice', ['count' => $created]))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__(':created created, :failed failed', ['created' => $created, 'failed' => count($errors)]))
                    ->body(implode("\n", array_slice($errors, 0, 5)))
                    ->danger()
                    ->persistent()
                    ->send();
            });
    }

    public function missingPowerOfficeAccountCount(): int
    {
        if (! is_array($this->powerOfficeAccountStatus)) {
            return 0;
        }

        return collect($this->powerOfficeAccountStatus['gl'] ?? [])->where('exists', false)->count()
            + collect($this->powerOfficeAccountStatus['suppliers'] ?? [])->where('exists', false)->count();
    }

    public function refreshIntegration(): void
    {
        $this->integration?->refresh();

        if ($this->shouldShowSettings()) {
            $this->markOnboardingCompleteIfConnected();
            $this->fillSettingsForm();
        }
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
                ->title(__('Onboarding failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Continue in PowerOffice'))
            ->body(__('Complete activation in the new window, then return here and click Refresh status. Ledger accounts are configured on this page once connected.'))
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
            ->forStore((int) $store->getKey())
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->first();

        if (! $session) {
            Notification::make()
                ->title(__('No closed sessions'))
                ->warning()
                ->send();

            return;
        }

        SyncPowerOfficeZReportJob::dispatch($session->id, true);

        Notification::make()
            ->title(__('Sync queued'))
            ->body('Session '.$session->session_number)
            ->success()
            ->send();
    }

    protected function defaultWizardSalesAccountForLine(string $label, string $basisKey): string
    {
        if ($this->integration?->mapping_basis === PowerOfficeMappingBasis::Vat) {
            return PowerOfficeLedgerDefaults::vatRateSalesAccounts()[$basisKey] ?? '';
        }

        if ($this->integration?->mapping_basis === PowerOfficeMappingBasis::Category) {
            if ($basisKey === '0') {
                return (string) (PowerOfficeLedgerDefaults::ledgerSettings()['default_sales_account_no'] ?? '');
            }

            return PowerOfficeLedgerDefaults::salesAccountForCollectionName($label) ?? '';
        }

        return '';
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

    /**
     * @return array<string, string>
     */
    protected function articleGroupLineOptions(): array
    {
        $store = Filament::getTenant();
        if (! $store) {
            return [];
        }

        return ArticleGroupCode::query()
            ->where(function ($query) use ($store): void {
                $query->where('stripe_account_id', $store->stripe_account_id)
                    ->orWhere(function ($query): void {
                        $query->whereNull('stripe_account_id')
                            ->where('is_standard', true);
                    });
            })
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ArticleGroupCode $code): array => [
                $code->code => $code->code.' — '.$code->name,
            ])
            ->all();
    }

    /**
     * @return list<array{code: string, name: string, sales_account: ?string, product_count: int, mapped: bool}>
     */
    public function articleGroupSetupRows(): array
    {
        $store = Filament::getTenant();
        if (! $store || ! $this->integration) {
            return [];
        }

        $mappings = $this->integration->accountMappings
            ->where('basis_type', PowerOfficeMappingBasis::ArticleGroup)
            ->keyBy('basis_key');

        $productCounts = ConnectedProduct::query()
            ->where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('article_group_code')
            ->selectRaw('article_group_code, count(*) as aggregate')
            ->groupBy('article_group_code')
            ->pluck('aggregate', 'article_group_code');

        return collect($this->articleGroupLineOptions())
            ->map(function (string $label, string $code) use ($mappings, $productCounts): array {
                $mapping = $mappings->get($code);
                $name = str_contains($label, ' — ') ? trim(explode(' — ', $label, 2)[1]) : $label;

                return [
                    'code' => $code,
                    'name' => $name,
                    'sales_account' => $mapping?->sales_account_no,
                    'product_count' => (int) ($productCounts[$code] ?? 0),
                    'mapped' => filled($mapping?->sales_account_no),
                ];
            })
            ->values()
            ->all();
    }

    protected function articleGroupCodesIndexUrl(): ?string
    {
        $store = Filament::getTenant();
        if (! $store) {
            return null;
        }

        return ArticleGroupCodeResource::getUrl('index', ['tenant' => $store]);
    }
}
