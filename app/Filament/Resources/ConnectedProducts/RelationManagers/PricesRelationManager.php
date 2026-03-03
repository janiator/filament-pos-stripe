<?php

namespace App\Filament\Resources\ConnectedProducts\RelationManagers;

use App\Models\ConnectedPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_product_id', $this->ownerRecord->stripe_product_id)
                ->where('stripe_account_id', $this->ownerRecord->stripe_account_id))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('unit_amount', $direction);
                    }),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->getStateUsing(fn (ConnectedPrice $record) => $this->ownerRecord->default_price === $record->stripe_price_id)
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        $defaultPriceId = $this->ownerRecord->default_price;
                        
                        // Sort by whether stripe_price_id matches the default_price
                        // When ascending: default prices (matches) come first (0), non-default come second (1)
                        // When descending: non-default prices come first (1), default come second (0)
                        if ($direction === 'asc') {
                            return $query->orderByRaw("CASE WHEN stripe_price_id = ? THEN 0 ELSE 1 END", [$defaultPriceId]);
                        } else {
                            return $query->orderByRaw("CASE WHEN stripe_price_id = ? THEN 1 ELSE 0 END", [$defaultPriceId]);
                        }
                    }),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->colors([
                        'success' => 'recurring',
                        'info' => 'one_time',
                    ])
                    ->sortable(),

                TextColumn::make('recurring_description')
                    ->label('Billing Interval')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->visible(fn (?ConnectedPrice $record) => $record && $record->type === 'recurring'),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('nickname')
                    ->label('Nickname')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('billing_scheme')
                    ->label('Billing Scheme')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (?ConnectedPrice $record) => $record && $record->billing_scheme),

                TextColumn::make('stripe_price_id')
                    ->label('Price ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'one_time' => 'One Time',
                        'recurring' => 'Recurring',
                    ]),
            ])
            ->headerActions([
                // Prices are typically created through Stripe API
            ])
            ->recordActions([
                // Prices are immutable in Stripe - view only
                ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\ConnectedPrices\ConnectedPriceResource::getUrl('view', ['record' => $record])),
                
                \Filament\Actions\Action::make('setAsDefault')
                    ->label('Set as Default')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedStar)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Set as Default Price')
                    ->modalDescription(fn (ConnectedPrice $record) => "Set this price as the default price for the product: {$record->formatted_amount}")
                    ->action(function (ConnectedPrice $record) {
                        $product = $this->ownerRecord;
                        
                        // Update the product's default_price
                        $product->default_price = $record->stripe_price_id;
                        
                        // Also update the product's price field to match the default price amount
                        // Convert from cents to decimal (e.g., 29900 -> 299.00)
                        $product->price = number_format($record->unit_amount / 100, 2, '.', '');
                        $product->currency = $record->currency;
                        
                        $product->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Default price updated')
                            ->body("Price {$record->formatted_amount} is now the default price for this product. The product price field has been updated to match.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ConnectedPrice $record) => $record->active && $this->ownerRecord->default_price !== $record->stripe_price_id),
                
                \Filament\Actions\Action::make('createPaymentLink')
                    ->label('Create Payment Link')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->color('success')
                    ->modalHeading('Create Payment Link')
                    ->modalDescription(fn (ConnectedPrice $record) => "Create a payment link for: {$record->formatted_amount}")
                    ->form(function (ConnectedPrice $record) {
                        // Get the store to access commission settings
                        $product = $this->ownerRecord;
                        $store = \App\Models\Store::where('stripe_account_id', $product->stripe_account_id)->first();
                        
                        // Determine default application fee based on store commission and price type
                        $defaultFeePercent = null;
                        $defaultFeeAmount = null;
                        
                        if ($store) {
                            if ($store->commission_type === 'percentage') {
                                $defaultFeePercent = $store->commission_rate;
                            } elseif ($store->commission_type === 'fixed') {
                                $defaultFeeAmount = $store->commission_rate;
                            }
                        }
                        
                        return [
                            TextInput::make('name')
                                ->label('Name')
                                ->maxLength(255)
                                ->helperText('Optional: A name for this payment link'),
                            Select::make('link_type')
                                ->label('Link Type')
                                ->options([
                                    'direct' => 'Direct',
                                    'destination' => 'Destination',
                                ])
                                ->default('direct')
                                ->required()
                                ->live()
                                ->helperText('Direct: Charge goes directly to connected account. Destination: Charge goes to platform with transfer.'),
                            TextInput::make('application_fee_percent')
                                ->label('Application Fee (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default($defaultFeePercent)
                                ->helperText(function () use ($record) {
                                    if ($record->type === 'recurring') {
                                        return 'Percentage fee (e.g., 5 = 5%). This will be applied as a percentage of each subscription invoice. Can only be used with recurring prices.';
                                    }
                                    return 'Percentage fee can only be used with recurring prices. Use fixed fee amount for one-time prices.';
                                })
                                ->visible(fn () => $record->type === 'recurring'),
                            TextInput::make('application_fee_amount')
                                ->label('Application Fee (øre)')
                                ->numeric()
                                ->minValue(0)
                                ->default($defaultFeeAmount)
                                ->helperText(function () use ($record) {
                                    if ($record->type === 'recurring') {
                                        return 'Fixed fee cannot be used with recurring prices. Use percentage fee instead.';
                                    }
                                    return 'Fixed fee in øre (e.g., 500 = 5,00 NOK). Can only be used with one-time prices.';
                                })
                                ->visible(fn () => $record->type !== 'recurring'),
                            TextInput::make('after_completion_redirect_url')
                                ->label('Redirect URL')
                                ->url()
                                ->helperText('Optional: URL to redirect to after payment completion'),
                            Toggle::make('adjustable_quantity_enabled')
                                ->label('Allow Customers to Adjust Quantity')
                                ->default(false)
                                ->helperText('Enable this to let customers change the quantity of items during checkout')
                                ->live(),
                            TextInput::make('adjustable_quantity_minimum')
                                ->label('Minimum Quantity')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->helperText('Minimum quantity customers can select (default: 1)')
                                ->visible(fn (Get $get) => $get('adjustable_quantity_enabled') === true)
                                ->required(fn (Get $get) => $get('adjustable_quantity_enabled') === true),
                            TextInput::make('adjustable_quantity_maximum')
                                ->label('Maximum Quantity')
                                ->numeric()
                                ->minValue(1)
                                ->default(99)
                                ->helperText('Maximum quantity customers can select (default: 99)')
                                ->visible(fn (Get $get) => $get('adjustable_quantity_enabled') === true)
                                ->required(fn (Get $get) => $get('adjustable_quantity_enabled') === true),
                        ];
                    })
                    ->action(function (ConnectedPrice $record, array $data) {
                        // Get the store from the product
                        $product = $this->ownerRecord;
                        
                        // Check if product is active (Stripe requires active products for payment links)
                        if (!$product->active) {
                            throw new \Exception('Cannot create payment link: The product associated with this price is not active. Please activate the product first.');
                        }
                        
                        $store = \App\Models\Store::where('stripe_account_id', $product->stripe_account_id)->first();
                        
                        if (!$store) {
                            throw new \Exception('Store not found for this product.');
                        }
                        
                        // Build line item (matching ConnectedPaymentLinkForm / CreateConnectedPaymentLink)
                        $lineItem = [
                            'price' => $record->stripe_price_id,
                            'quantity' => 1,
                        ];
                        if (!empty($data['adjustable_quantity_enabled'])) {
                            $lineItem['adjustable_quantity'] = ['enabled' => true];
                            if (isset($data['adjustable_quantity_minimum']) && $data['adjustable_quantity_minimum'] !== null && $data['adjustable_quantity_minimum'] !== '') {
                                $lineItem['adjustable_quantity']['minimum'] = (int) $data['adjustable_quantity_minimum'];
                            }
                            if (isset($data['adjustable_quantity_maximum']) && $data['adjustable_quantity_maximum'] !== null && $data['adjustable_quantity_maximum'] !== '') {
                                $lineItem['adjustable_quantity']['maximum'] = (int) $data['adjustable_quantity_maximum'];
                            }
                        }
                        $lineItems = [$lineItem];
                        
                        $linkData = [
                            'line_items' => $lineItems,
                            'name' => $data['name'] ?? null,
                            'link_type' => $data['link_type'] ?? 'direct',
                            'after_completion_redirect_url' => $data['after_completion_redirect_url'] ?? null,
                        ];
                        
                        // Add application fee (works for both direct and destination links)
                        if ($record->type === 'recurring') {
                            if (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                                $linkData['application_fee_percent'] = (float) $data['application_fee_percent'];
                            }
                        } else {
                            if (isset($data['application_fee_amount']) && $data['application_fee_amount'] !== null && $data['application_fee_amount'] !== '') {
                                $linkData['application_fee_amount'] = (int) $data['application_fee_amount'];
                            } elseif (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                                $feePercent = (float) $data['application_fee_percent'];
                                $linkData['application_fee_amount'] = (int) round(($record->unit_amount * $feePercent) / 100);
                            }
                        }
                        
                        $action = new \App\Actions\ConnectedPaymentLinks\CreateConnectedPaymentLinkOnStripe();
                        $paymentLink = $action($store, $linkData, true);
                        
                        if (!$paymentLink) {
                            throw new \Exception('Failed to create payment link on Stripe.');
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment link created')
                            ->body('Payment link created successfully.')
                            ->success()
                            ->action(
                                'View Payment Link',
                                \App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource::getUrl('view', ['record' => $paymentLink])
                            )
                            ->send();
                    })
                    ->visible(fn (ConnectedPrice $record) => $record->active),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
