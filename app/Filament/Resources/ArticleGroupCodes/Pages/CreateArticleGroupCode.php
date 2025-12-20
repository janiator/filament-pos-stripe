<?php

namespace App\Filament\Resources\ArticleGroupCodes\Pages;

use App\Filament\Resources\ArticleGroupCodes\ArticleGroupCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleGroupCode extends CreateRecord
{
    protected static string $resource = ArticleGroupCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set store_id and stripe_account_id from tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $data['store_id'] = $tenant->id;
            $data['stripe_account_id'] = $tenant->stripe_account_id;
        }

        // Ensure stripe_account_id is set (or allow global standard codes)
        if (empty($data['stripe_account_id']) && empty($data['is_standard'])) {
            // Allow global standard codes to be created without stripe_account_id
            if (!isset($data['is_standard']) || !$data['is_standard']) {
                throw new \Exception('Cannot create article group code: stripe_account_id is required for non-standard codes. Please ensure you are creating the code within a store context.');
            }
        }

        return $data;
    }
}
