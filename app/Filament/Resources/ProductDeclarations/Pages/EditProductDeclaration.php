<?php

namespace App\Filament\Resources\ProductDeclarations\Pages;

use App\Filament\Resources\ProductDeclarations\ProductDeclarationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductDeclaration extends EditRecord
{
    protected static string $resource = ProductDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Deactivate other active declarations for this store when activating this one
        if (($data['is_active'] ?? false) && isset($this->record->store_id)) {
            \App\Models\ProductDeclaration::where('store_id', $this->record->store_id)
                ->where('id', '!=', $this->record->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $data;
    }
}
