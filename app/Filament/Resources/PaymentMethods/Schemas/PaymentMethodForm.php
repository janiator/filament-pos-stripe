<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Models\Store;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->required()
                    ->disabled(fn ($record) => $record !== null) // Can't change store after creation
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                    ->searchable()
                    ->preload()
                    ->visible(function () {
                        try {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            return $tenant && $tenant->slug === 'visivo-admin';
                        } catch (\Throwable $e) {
                            return false;
                        }
                    }),
                TextInput::make('name')
                    ->label('Display Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Kontant, Kort, Mobile Pay'),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: \App\Models\PaymentMethod::class,
                        ignoreRecord: true,
                        modifyRuleUsing: function ($rule, $get, $record) {
                            // Get store_id from form data, record, or tenant
                            $storeId = $get('store_id') 
                                ?? $record?->store_id 
                                ?? \Filament\Facades\Filament::getTenant()?->id;
                            
                            if ($storeId) {
                                return $rule->where('store_id', $storeId);
                            }
                            
                            return $rule;
                        }
                    )
                    ->placeholder('e.g., cash, card, mobile')
                    ->helperText('Internal code used to identify this payment method')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        // Auto-fill SAF-T codes when code or provider_method changes
                        $providerMethod = $get('provider_method');
                        if ($state) {
                            $paymentCode = \App\Services\SafTCodeMapper::mapPaymentMethodToCode($state, $providerMethod);
                            $eventCode = \App\Services\SafTCodeMapper::mapPaymentMethodToEventCode($state, $providerMethod);
                            $set('saf_t_payment_code', $paymentCode);
                            $set('saf_t_event_code', $eventCode);
                        }
                    }),
                Select::make('provider')
                    ->label('Provider')
                    ->options([
                        'stripe' => 'Stripe',
                        'cash' => 'Cash',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->default('other')
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => $set('provider_method', null)),
                Select::make('provider_method')
                    ->label('Provider Method')
                    ->options(function ($get) {
                        $provider = $get('provider');
                        if ($provider === 'stripe') {
                            return [
                                'card_present' => 'Card Present (Terminal)',
                                'card' => 'Card (Online)',
                                'us_bank_account' => 'US Bank Account',
                                'sepa_debit' => 'SEPA Debit',
                                'link' => 'Link (Stripe)',
                            ];
                        }
                        return [];
                    })
                    ->visible(fn ($get) => $get('provider') === 'stripe')
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        // Auto-fill SAF-T codes when provider_method changes
                        $code = $get('code');
                        if ($code && $state) {
                            $paymentCode = \App\Services\SafTCodeMapper::mapPaymentMethodToCode($code, $state);
                            $eventCode = \App\Services\SafTCodeMapper::mapPaymentMethodToEventCode($code, $state);
                            $set('saf_t_payment_code', $paymentCode);
                            $set('saf_t_event_code', $eventCode);
                        }
                    }),
                Toggle::make('enabled')
                    ->label('Enabled')
                    ->default(true)
                    ->helperText('Disable to hide this payment method from POS'),
                Toggle::make('pos_suitable')
                    ->label('POS Suitable')
                    ->default(true)
                    ->helperText('Enable if this payment method is suitable for physical POS. Disable for online-only methods (e.g., online card payments).'),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first in the payment method list'),
                Select::make('saf_t_payment_code')
                    ->label('SAF-T Payment Code')
                    ->options(\App\Services\SafTCodeMapper::getPaymentCodes())
                    ->searchable()
                    ->helperText('PredefinedBasicID-12 code for SAF-T compliance. Auto-filled based on payment method code, but can be overridden.')
                    ->required(),
                Select::make('saf_t_event_code')
                    ->label('SAF-T Event Code')
                    ->options([
                        '13016' => '13016 - Cash payment (Kontantbetaling)',
                        '13017' => '13017 - Card payment (Kortbetaling)',
                        '13018' => '13018 - Mobile payment (Mobilbetaling)',
                        '13019' => '13019 - Other payment method (Annen betalingsmåte)',
                    ])
                    ->searchable()
                    ->helperText('PredefinedBasicID-13 code for SAF-T compliance. Auto-filled based on payment method code, but can be overridden.')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        ColorPicker::make('background_color')
                            ->label('Background Color')
                            ->rgba()
                            ->helperText('Accent background color for the payment method button (supports #AARRGGBB or rgba format)')
                            ->default('rgba(76, 75, 57, 0.94)')
                            ->rules([
                                'nullable',
                                'regex:/^(#[0-9A-Fa-f]{8}|rgba?\(\d+,\s*\d+,\s*\d+(?:,\s*[\d.]+)?\))$/',
                            ])
                            ->dehydrateStateUsing(function ($state) {
                                // Convert rgba() to #AARRGGBB if needed
                                if (preg_match('/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/', $state, $matches)) {
                                    $r = (int)$matches[1];
                                    $g = (int)$matches[2];
                                    $b = (int)$matches[3];
                                    $a = isset($matches[4]) ? (float)$matches[4] : 1.0;
                                    $aHex = str_pad(dechex(round($a * 255)), 2, '0', STR_PAD_LEFT);
                                    return sprintf('#%s%02X%02X%02X', $aHex, $r, $g, $b);
                                }
                                // If already in #AARRGGBB or #RRGGBB format, return as is
                                if (preg_match('/^#[0-9A-Fa-f]{6,8}$/', $state)) {
                                    return $state;
                                }
                                return $state;
                            })
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return null;
                                }
                                // Convert #AARRGGBB to rgba() for display (alpha is first in this format)
                                if (preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $state, $matches)) {
                                    $a = round(hexdec($matches[1]) / 255, 2);
                                    $r = hexdec($matches[2]);
                                    $g = hexdec($matches[3]);
                                    $b = hexdec($matches[4]);
                                    return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $a);
                                }
                                // If #RRGGBB (no alpha), convert to rgba with alpha 1.0
                                if (preg_match('/^#([0-9A-Fa-f]{6})$/', $state, $matches)) {
                                    $r = hexdec(substr($matches[1], 0, 2));
                                    $g = hexdec(substr($matches[1], 2, 2));
                                    $b = hexdec(substr($matches[1], 4, 2));
                                    return sprintf('rgba(%d, %d, %d, 1.0)', $r, $g, $b);
                                }
                                // If already rgba, return as is
                                if (preg_match('/^rgba?\(/', $state)) {
                                    return $state;
                                }
                                return $state;
                            }),
                        ColorPicker::make('icon_color')
                            ->label('Icon Color')
                            ->rgba()
                            ->helperText('Color for the payment method icon (supports #RRGGBB or rgba format)')
                            ->default('rgba(39, 43, 61, 1.0)')
                            ->rules([
                                'nullable',
                                'regex:/^(#[0-9A-Fa-f]{6}(?:[0-9A-Fa-f]{2})?|rgba?\(\d+,\s*\d+,\s*\d+(?:,\s*[\d.]+)?\))$/',
                            ])
                            ->dehydrateStateUsing(function ($state) {
                                // Convert rgba() to #RRGGBB (no alpha for icon color)
                                if (preg_match('/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/', $state, $matches)) {
                                    $r = (int)$matches[1];
                                    $g = (int)$matches[2];
                                    $b = (int)$matches[3];
                                    return sprintf('#%02X%02X%02X', $r, $g, $b);
                                }
                                // If already in #RRGGBB or #AARRGGBB format, return #RRGGBB
                                if (preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})?$/', $state, $matches)) {
                                    return sprintf('#%s%s%s', $matches[1], $matches[2], $matches[3]);
                                }
                                if (preg_match('/^#([0-9A-Fa-f]{6})$/', $state)) {
                                    return $state;
                                }
                                return $state;
                            })
                            ->formatStateUsing(function ($state) {
                                // Convert #RRGGBB or #AARRGGBB to rgba() for display
                                if (preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})?$/', $state, $matches)) {
                                    $r = hexdec($matches[1]);
                                    $g = hexdec($matches[2]);
                                    $b = hexdec($matches[3]);
                                    return sprintf('rgba(%d, %d, %d, 1.0)', $r, $g, $b);
                                }
                                if (preg_match('/^#([0-9A-Fa-f]{6})$/', $state, $matches)) {
                                    $r = hexdec(substr($matches[1], 0, 2));
                                    $g = hexdec(substr($matches[1], 2, 2));
                                    $b = hexdec(substr($matches[1], 4, 2));
                                    return sprintf('rgba(%d, %d, %d, 1.0)', $r, $g, $b);
                                }
                                if (preg_match('/^rgba?\(/', $state)) {
                                    return $state;
                                }
                                return $state;
                            }),
                    ]),
            ]);
    }
}
