<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set store_id and stripe_account_id from tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->id;
            $data['stripe_account_id'] = $tenant->stripe_account_id;
        }

        // Auto-generate handle from name if not provided
        if (empty($data['handle']) && !empty($data['name'])) {
            $data['handle'] = Str::slug($data['name']);
        }

        // Set image_url from uploaded image file if present
        if (isset($data['image']) && $data['image']) {
            // The image field contains the relative path (e.g., collections/file.jpg)
            $data['image_url'] = Storage::disk('public')->url($data['image']);
        }

        // Remove image field as it's not a database column
        unset($data['image']);

        return $data;
    }
}

