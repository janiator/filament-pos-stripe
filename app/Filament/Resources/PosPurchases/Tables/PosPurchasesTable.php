<?php

namespace App\Filament\Resources\PosPurchases\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['posSession', 'store', 'receipt']);
            })
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    })
                    ->toggleable(),

                TextColumn::make('posSession.session_number')
                    ->label('Session')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('receipt.receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('No receipt')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color(fn ($state) => match ($state) {
                        'cash' => 'success',
                        'card_present' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('charge_display')
                    ->label('Charge ID')
                    ->formatStateUsing(function ($record) {
                        if ($record->stripe_charge_id) {
                            return $record->stripe_charge_id;
                        }

                        return 'Cash #'.$record->id;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('stripe_charge_id', 'like', "%{$search}%")
                                ->orWhere('id', 'like', "%{$search}%");
                        });
                    })
                    ->copyable()
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'succeeded',
                        'warning' => 'pending',
                        'danger' => ['failed', 'refunded'],
                        'info' => 'processing',
                    ])
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('posSession.user.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('note')
                    ->label('Note')
                    ->state(function ($record) {
                        $metadata = $record->metadata ?? [];
                        if (is_string($metadata)) {
                            $metadata = json_decode($metadata, true) ?? [];
                        }

                        return is_array($metadata) ? ($metadata['note'] ?? null) : null;
                    })
                    ->wrap()
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $metadata = $record->metadata ?? [];
                        if (is_string($metadata)) {
                            $metadata = json_decode($metadata, true) ?? [];
                        }

                        return is_array($metadata) ? ($metadata['note'] ?? null) : null;
                    })
                    ->placeholder('-')
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
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options(function () {
                        return \App\Models\ConnectedCharge::whereNotNull('pos_session_id')
                            ->distinct()
                            ->pluck('payment_method')
                            ->filter()
                            ->mapWithKeys(fn ($method) => [$method => ucfirst(str_replace('_', ' ', $method))])
                            ->toArray();
                    }),

                Filter::make('cashier')
                    ->label('Cashier')
                    ->form([
                        Select::make('user_id')
                            ->label('Cashier')
                            ->options(function () {
                                $query = \App\Models\PosSession::query()
                                    ->whereNotNull('user_id')
                                    ->select('user_id')
                                    ->distinct();
                                $tenant = \Filament\Facades\Filament::getTenant();
                                if ($tenant && $tenant->slug !== 'visivo-admin') {
                                    $query->where('store_id', $tenant->id);
                                }
                                $userIds = $query->pluck('user_id')->filter()->unique()->values()->all();

                                return \App\Models\User::query()
                                    ->whereIn('id', $userIds)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['user_id']),
                            fn (Builder $query): Builder => $query->whereHas(
                                'posSession',
                                fn (Builder $q) => $q->where('user_id', $data['user_id']),
                            ),
                        );
                    }),

                Filter::make('has_receipt')
                    ->label('Receipt')
                    ->form([
                        Select::make('has_receipt')
                            ->label('Has receipt')
                            ->options([
                                '' => 'All',
                                '1' => 'With receipt',
                                '0' => 'Without receipt',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['has_receipt'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === '1'
                            ? $query->whereHas('receipt')
                            : $query->whereDoesntHave('receipt');
                    }),

                Filter::make('amount')
                    ->label('Amount range')
                    ->form([
                        TextInput::make('amount_min')
                            ->label('Min (main unit, e.g. NOK)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('amount_max')
                            ->label('Max (main unit, e.g. NOK)')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['amount_min']),
                                fn (Builder $query, $ignored): Builder => $query->where('amount', '>=', (int) round((float) $data['amount_min'] * 100)),
                            )
                            ->when(
                                filled($data['amount_max']),
                                fn (Builder $query, $ignored): Builder => $query->where('amount', '<=', (int) round((float) $data['amount_max'] * 100)),
                            );
                    }),

                Filter::make('pos_session_id')
                    ->label('POS Session')
                    ->form([
                        Select::make('pos_session_id')
                            ->relationship('posSession', 'session_number', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->where('pos_sessions.store_id', $tenant->id);
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback
                                }
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['pos_session_id'] ?? null,
                            fn (Builder $query, $sessionId): Builder => $query->where('pos_session_id', $sessionId),
                        );
                    }),

                Filter::make('created_at')
                    ->label('Created')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created From'),
                        DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('paid_at')
                    ->label('Paid at')
                    ->form([
                        DatePicker::make('paid_from')
                            ->label('Paid From'),
                        DatePicker::make('paid_until')
                            ->label('Paid Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '>=', $date),
                            )
                            ->when(
                                $data['paid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // Purchases should not be editable for audit trail compliance
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                // No bulk actions - purchases cannot be deleted per kassasystemforskriften
            ]);
    }
}
