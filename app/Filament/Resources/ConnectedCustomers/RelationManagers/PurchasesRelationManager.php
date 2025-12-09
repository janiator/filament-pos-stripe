<?php

namespace App\Filament\Resources\ConnectedCustomers\RelationManagers;

use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use App\Models\ConnectedCharge;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $title = 'Purchases';

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
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_customer_id', $this->ownerRecord->stripe_customer_id)
                ->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                ->with(['posSession', 'receipt', 'store']))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'succeeded',
                        'warning' => 'pending',
                        'danger' => ['failed', 'refunded'],
                        'info' => 'processing',
                    ])
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('posSession.session_number')
                    ->label('POS Session')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('receipt.receipt_number')
                    ->label('Receipt')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('stripe_charge_id')
                    ->label('Charge ID')
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                        'processing' => 'Processing',
                    ]),

                SelectFilter::make('has_pos_session')
                    ->label('POS Purchase')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->query(function ($query, array $data) {
                        if (isset($data['value'])) {
                            if ($data['value'] === '1') {
                                $query->whereNotNull('pos_session_id');
                            } else {
                                $query->whereNull('pos_session_id');
                            }
                        }
                    }),
            ])
            ->headerActions([
                // Purchases are typically created via API/POS
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ConnectedChargeResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    // Bulk delete removed for kassasystemforskriften compliance (§ 2-6)
                    // Transactions cannot be deleted per Norwegian cash register regulations
                ]),
            ]);
    }
}
