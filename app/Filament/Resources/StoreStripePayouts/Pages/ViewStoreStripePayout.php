<?php

namespace App\Filament\Resources\StoreStripePayouts\Pages;

use App\Filament\Resources\StoreStripePayouts\StoreStripePayoutResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStoreStripePayout extends ViewRecord
{
    protected static string $resource = StoreStripePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
