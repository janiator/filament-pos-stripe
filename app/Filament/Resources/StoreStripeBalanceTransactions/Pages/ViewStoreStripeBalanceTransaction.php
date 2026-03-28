<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Pages;

use App\Filament\Resources\StoreStripeBalanceTransactions\StoreStripeBalanceTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStoreStripeBalanceTransaction extends ViewRecord
{
    protected static string $resource = StoreStripeBalanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
