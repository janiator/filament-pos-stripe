<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedPaymentLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stripe_account_id')
                    ->label('Store')
                    ->options(function () {
                        $query = \App\Models\Store::whereNotNull('stripe_account_id');
                        
                        // Scope to tenant if not admin
                        try {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            if ($tenant && $tenant->slug !== 'visivo-admin') {
                                $query->where('id', $tenant->id);
                            }
                        } catch (\Throwable $e) {
                            // Fallback if Filament facade not available
                        }
                        
                        return $query->pluck('name', 'stripe_account_id');
                    })
                    ->default(function () {
                        try {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            return $tenant?->stripe_account_id;
                        } catch (\Throwable $e) {
                            return null;
                        }
                    })
                    ->required()
                    ->helperText('The store/connected account this payment link belongs to')
                    ->live()
                    ->hidden()
                    ->dehydrated(true),

                Select::make('stripe_price_id')
                    ->label('Price')
                    ->options(function (Get $get) {
                        // Get account ID from form state or tenant
                        $accountId = $get('stripe_account_id');
                        
                        if (! $accountId) {
                            // Fallback to tenant's stripe_account_id
                            try {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                $accountId = $tenant?->stripe_account_id;
                            } catch (\Throwable $e) {
                                // Fallback
                            }
                        }
                        
                        if (! $accountId) {
                            return [];
                        }

                        // Load all active prices without limit
                        $prices = \App\Models\ConnectedPrice::where('stripe_account_id', $accountId)
                            ->where('active', true)
                            ->get();
                        
                        // Load products separately to ensure proper relationship loading
                        $productIds = $prices->pluck('stripe_product_id')->unique()->filter();
                        $products = \App\Models\ConnectedProduct::where('stripe_account_id', $accountId)
                            ->whereIn('stripe_product_id', $productIds)
                            ->get()
                            ->keyBy('stripe_product_id');
                        
                        return $prices->mapWithKeys(function ($price) use ($products) {
                            $product = $products->get($price->stripe_product_id);
                            $productName = $product?->name ?? 'Unknown Product';
                            $amount = $price->formatted_amount;
                            $recurring = $price->recurring_description;
                            $label = "{$productName} - {$amount}";
                            if ($recurring) {
                                $label .= " ({$recurring})";
                            }
                            return [$price->stripe_price_id => $label];
                        });
                    })
                    ->getSearchResultsUsing(function (string $search, Get $get) {
                        // Get account ID from form state or tenant
                        $accountId = $get('stripe_account_id');
                        
                        if (! $accountId) {
                            // Fallback to tenant's stripe_account_id
                            try {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                $accountId = $tenant?->stripe_account_id;
                            } catch (\Throwable $e) {
                                // Fallback
                            }
                        }
                        
                        if (! $accountId) {
                            return [];
                        }

                        // Search in multiple fields: product name, price ID, nickname, formatted amount
                        $searchLower = strtolower($search);
                        
                        // First, find products that match the search term (case-insensitive)
                        $matchingProducts = \App\Models\ConnectedProduct::where('stripe_account_id', $accountId)
                            ->where(function ($query) use ($search, $searchLower) {
                                $query->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                                      ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"]);
                            })
                            ->pluck('stripe_product_id')
                            ->toArray();
                        
                        // Then find prices that match:
                        // 1. Belong to products matching the search term
                        // 2. Have a stripe_price_id matching the search term
                        // 3. Have a nickname matching the search term
                        // 4. Have formatted amount matching the search term
                        $prices = \App\Models\ConnectedPrice::where('stripe_account_id', $accountId)
                            ->where('active', true)
                            ->where(function ($query) use ($search, $searchLower, $matchingProducts) {
                                if (!empty($matchingProducts)) {
                                    $query->whereIn('stripe_product_id', $matchingProducts);
                                }
                                $query->orWhereRaw('LOWER(stripe_price_id) LIKE ?', ["%{$searchLower}%"]);
                                if ($search) {
                                    // Try to match formatted amount (e.g., "10.00" or "10,00")
                                    $numericSearch = preg_replace('/[^0-9.,]/', '', $search);
                                    if ($numericSearch) {
                                        // Convert to cents for comparison
                                        $amountInCents = (int) round((float) str_replace(',', '.', $numericSearch) * 100);
                                        if ($amountInCents > 0) {
                                            $query->orWhere('unit_amount', $amountInCents);
                                        }
                                    }
                                }
                                if (!empty($search)) {
                                    $query->orWhereRaw('LOWER(nickname) LIKE ?', ["%{$searchLower}%"]);
                                }
                            })
                            ->get(); // Remove limit to show all matching results
                        
                        // Load all products for the prices to ensure proper relationship loading
                        $productIds = $prices->pluck('stripe_product_id')->unique()->filter();
                        $products = \App\Models\ConnectedProduct::where('stripe_account_id', $accountId)
                            ->whereIn('stripe_product_id', $productIds)
                            ->get()
                            ->keyBy('stripe_product_id');
                        
                        return $prices->mapWithKeys(function ($price) use ($products) {
                            $product = $products->get($price->stripe_product_id);
                            $productName = $product?->name ?? 'Unknown Product';
                            $amount = $price->formatted_amount;
                            $recurring = $price->recurring_description;
                            $label = "{$productName} - {$amount}";
                            if ($recurring) {
                                $label .= " ({$recurring})";
                            }
                            return [$price->stripe_price_id => $label];
                        });
                    })
                    ->getOptionLabelUsing(function ($value, Get $get) {
                        if (! $value) {
                            return null;
                        }
                        
                        // Get account ID from form state or tenant
                        $accountId = $get('stripe_account_id');
                        
                        if (! $accountId) {
                            // Fallback to tenant's stripe_account_id
                            try {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                $accountId = $tenant?->stripe_account_id;
                            } catch (\Throwable $e) {
                                // Fallback
                            }
                        }
                        
                        if (! $accountId) {
                            return $value;
                        }

                        $price = \App\Models\ConnectedPrice::where('stripe_price_id', $value)
                            ->where('stripe_account_id', $accountId)
                            ->first();
                        
                        if (! $price) {
                            return $value;
                        }

                        // Load product separately to ensure proper relationship loading
                        $product = \App\Models\ConnectedProduct::where('stripe_product_id', $price->stripe_product_id)
                            ->where('stripe_account_id', $accountId)
                            ->first();
                        
                        $productName = $product?->name ?? 'Unknown Product';
                        $amount = $price->formatted_amount;
                        $recurring = $price->recurring_description;
                        $label = "{$productName} - {$amount}";
                        if ($recurring) {
                            $label .= " ({$recurring})";
                        }
                        return $label;
                    })
                    ->searchable()
                    ->required()
                    ->helperText('Select a price from this store')
                    ->live()
                    ->visibleOn('create'),

                TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->helperText('Optional: A name for this payment link')
                    ->visibleOn('create'),

                Select::make('link_type')
                    ->label('Link Type')
                    ->options([
                        'direct' => 'Direct',
                        'destination' => 'Destination',
                    ])
                    ->default('direct')
                    ->required()
                    ->live()
                    ->helperText('Direct: Charge goes directly to connected account. Destination: Charge goes to platform with transfer.')
                    ->visibleOn('create'),

                TextInput::make('application_fee_percent')
                    ->label('Application Fee (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->helperText(function (Get $get) {
                        $priceId = $get('stripe_price_id');
                        if (!$priceId) {
                            return 'Percentage fee (e.g., 5 = 5%). Can only be used with recurring prices.';
                        }
                        
                        // Check if price is recurring
                        $price = \App\Models\ConnectedPrice::where('stripe_price_id', $priceId)->first();
                        if ($price && $price->type === 'recurring') {
                            return 'Percentage fee (e.g., 5 = 5%). This will be applied as a percentage of each subscription invoice. Can only be used with recurring prices.';
                        }
                        
                        return 'Percentage fee can only be used with recurring prices. Use fixed fee amount for one-time prices.';
                    })
                    ->visible(function (Get $get) {
                        $priceId = $get('stripe_price_id');
                        if (!$priceId) {
                            return false; // Hide if no price selected
                        }
                        
                        // Only show for recurring prices
                        $price = \App\Models\ConnectedPrice::where('stripe_price_id', $priceId)->first();
                        return $price && $price->type === 'recurring';
                    })
                    ->visibleOn('create'),

                TextInput::make('application_fee_amount')
                    ->label('Application Fee (cents)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText(function (Get $get) {
                        $priceId = $get('stripe_price_id');
                        if (!$priceId) {
                            return 'Fixed fee in cents (e.g., 500 = $5.00). Can only be used with one-time prices.';
                        }
                        
                        // Check if price is recurring
                        $price = \App\Models\ConnectedPrice::where('stripe_price_id', $priceId)->first();
                        if ($price && $price->type === 'recurring') {
                            return 'Fixed fee cannot be used with recurring prices. Use percentage fee instead.';
                        }
                        
                        return 'Fixed fee in cents (e.g., 500 = $5.00). Can only be used with one-time prices.';
                    })
                    ->visible(function (Get $get) {
                        $priceId = $get('stripe_price_id');
                        if (!$priceId) {
                            return true; // Show if no price selected yet
                        }
                        
                        // Only show for one-time prices
                        $price = \App\Models\ConnectedPrice::where('stripe_price_id', $priceId)->first();
                        return !($price && $price->type === 'recurring');
                    })
                    ->visibleOn('create'),

                TextInput::make('after_completion_redirect_url')
                    ->label('Redirect URL')
                    ->url()
                    ->helperText('Optional: URL to redirect to after payment completion')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('stripe_payment_link_id')
                    ->label('Payment Link ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('name')
                    ->label('Name')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('url')
                    ->label('URL')
                    ->disabled()
                    ->dehydrated(false)
                    ->url()
                    ->visibleOn('edit'),

                Toggle::make('active')
                    ->label('Active')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('link_type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->visibleOn('edit'),
            ]);
    }
}
