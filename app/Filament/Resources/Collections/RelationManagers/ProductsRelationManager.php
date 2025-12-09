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
use Filament\Forms\Components\CheckboxList;
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
                    ->form([
                        CheckboxList::make('recordIds')
                            ->label('Products')
                            ->options(function () {
                                // Get products that are not already in this collection
                                $existingProductIds = DB::table('collection_product')
                                    ->where('collection_id', $this->ownerRecord->id)
                                    ->pluck('connected_product_id');
                                
                                // Build query to get available products
                                $products = ConnectedProduct::query()
                                    ->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                                    ->where('active', true)
                                    ->when($existingProductIds->isNotEmpty(), function ($q) use ($existingProductIds) {
                                        return $q->whereNotIn('id', $existingProductIds);
                                    })
                                    ->orderBy('name', 'asc')
                                    ->orderBy('id', 'asc')
                                    ->get();
                                
                                return $products->mapWithKeys(fn ($product) => [$product->id => $product->name])->all();
                            })
                            ->searchable()
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText('Select products to add to this collection')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        $recordIds = $data['recordIds'] ?? [];
                        if (!empty($recordIds)) {
                            $this->ownerRecord->products()->attach($recordIds);
                        }
                    }),
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

