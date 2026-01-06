<?php

namespace App\Filament\Resources\ConnectedProducts\RelationManagers;

use App\Models\ConnectedPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                
                \Filament\Actions\Action::make('createPaymentLink')
                    ->label('Create Payment Link')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->color('success')
                    ->modalHeading('Create Payment Link')
                    ->modalDescription(fn (ConnectedPrice $record) => "Create a payment link for: {$record->formatted_amount}")
                    ->form(function (ConnectedPrice $record) {
                        return [
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
                            
                            TextInput::make('name')
                                ->label('Name')
                                ->maxLength(255)
                                ->helperText('Optional: A name for this payment link'),
                            
                            TextInput::make('application_fee_percent')
                                ->label('Application Fee (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->visible(fn (Get $get) => $get('link_type') === 'destination' && $record->type === 'recurring')
                                ->helperText('For destination links with recurring prices: Percentage fee (e.g., 5 = 5%)'),
                            
                            TextInput::make('application_fee_amount')
                                ->label('Application Fee (cents)')
                                ->numeric()
                                ->minValue(0)
                                ->visible(fn (Get $get) => $get('link_type') === 'destination' && $record->type !== 'recurring')
                                ->helperText('For destination links with one-time prices: Fixed fee in cents (e.g., 500 = $5.00)'),
                            
                            TextInput::make('after_completion_redirect_url')
                                ->label('Redirect URL')
                                ->url()
                                ->helperText('Optional: URL to redirect to after payment completion'),
                        ];
                    })
                    ->action(function (ConnectedPrice $record, array $data) {
                        // Get the store from the product
                        $product = $this->ownerRecord;
                        $store = \App\Models\Store::where('stripe_account_id', $product->stripe_account_id)->first();
                        
                        if (!$store) {
                            throw new \Exception('Store not found for this product.');
                        }
                        
                        // Prepare line items
                        $lineItems = [
                            [
                                'price' => $record->stripe_price_id,
                                'quantity' => 1,
                            ],
                        ];
                        
                        $linkData = [
                            'line_items' => $lineItems,
                            'name' => $data['name'] ?? null,
                            'link_type' => $data['link_type'] ?? 'direct',
                            'after_completion_redirect_url' => $data['after_completion_redirect_url'] ?? null,
                        ];
                        
                        // Add application fee for destination links
                        if ($linkData['link_type'] === 'destination') {
                            if ($record->type === 'recurring') {
                                if (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                                    $linkData['application_fee_percent'] = (float) $data['application_fee_percent'];
                                }
                            } else {
                                if (isset($data['application_fee_amount']) && $data['application_fee_amount'] !== null && $data['application_fee_amount'] !== '') {
                                    $linkData['application_fee_amount'] = (int) $data['application_fee_amount'];
                                } elseif (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                                    $feePercent = (float) $data['application_fee_percent'];
                                    $feeAmount = (int) round(($record->unit_amount * $feePercent) / 100);
                                    $linkData['application_fee_amount'] = $feeAmount;
                                }
                            }
                        }
                        
                        $action = new \App\Actions\ConnectedPaymentLinks\CreateConnectedPaymentLinkOnStripe();
                        $paymentLink = $action($store, $linkData, true);
                        
                        if (!$paymentLink) {
                            throw new \Exception('Failed to create payment link on Stripe.');
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment link created')
                            ->body('Payment link created successfully. Click to view.')
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('View Payment Link')
                                    ->url(\App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource::getUrl('view', ['record' => $paymentLink]))
                                    ->button(),
                            ])
                            ->send();
                    })
                    ->visible(fn (ConnectedPrice $record) => $record->active),
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
