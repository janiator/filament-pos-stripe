<?php

namespace App\Filament\Resources\ProductDeclarations\Pages;

use App\Filament\Resources\ProductDeclarations\ProductDeclarationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductDeclarations extends ListRecords
{
    protected static string $resource = ProductDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
