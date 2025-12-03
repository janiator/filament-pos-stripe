<?php

namespace App\Filament\Resources\Collections\RelationManagers;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Models\ConnectedProduct;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\Facades\DB;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Products';

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
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_account_id', $this->ownerRecord->stripe_account_id))
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->money('nok')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->recordSelect(function ($select) {
                        // Configure the select component with search and multi-select
                        return $select
                            ->searchable()
                            ->multiple()
                            ->getOptionLabelUsing(function ($value) {
                                // Load record directly without using the relationship
                                $product = ConnectedProduct::where('id', $value)
                                    ->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                                    ->first();
                                
                                return $product ? $product->name : 'Unknown Product';
                            })
                            ->getOptionLabelsUsing(function (array $values) {
                                // Load multiple records directly without using the relationship
                                $products = ConnectedProduct::whereIn('id', $values)
                                    ->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                                    ->get()
                                    ->mapWithKeys(fn ($product) => [$product->id => $product->name])
                                    ->all();
                                
                                return $products;
                            });
                    })
                    ->recordSelectOptionsQuery(function ($query) {
                        // Get products that are not already in this collection
                        // Use a fresh query to avoid the relationship's orderByPivot
                        $existingProductIds = DB::table('collection_product')
                            ->where('collection_id', $this->ownerRecord->id)
                            ->pluck('connected_product_id');
                        
                        // Build query from scratch to avoid relationship ordering issues
                        // This prevents DISTINCT/JSON column issues with PostgreSQL
                        return ConnectedProduct::query()
                            ->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                            ->where('active', true)
                            ->when($existingProductIds->isNotEmpty(), function ($q) use ($existingProductIds) {
                                return $q->whereNotIn('id', $existingProductIds);
                            })
                            ->orderBy('name', 'asc')
                            ->orderBy('id', 'asc');
                    })
                    ->recordSelectSearchColumns(['name', 'description']),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

