<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Set image_url from uploaded image file if present, or clear it if image was removed
        if (isset($data['image'])) {
            if ($data['image']) {
                // The image field contains the relative path (e.g., collections/file.jpg)
                $data['image_url'] = Storage::disk('public')->url($data['image']);
            } else {
                // Image was removed, clear image_url
                $data['image_url'] = null;
            }
        }

        // Remove image field as it's not a database column
        unset($data['image']);

        return $data;
    }
}


