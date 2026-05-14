<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Pages;

use App\Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\StoreStripeBalanceTransactions\StoreStripeBalanceTransactionResource;

class ViewStoreStripeBalanceTransaction extends ViewRecord
{
    protected static string $resource = StoreStripeBalanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
