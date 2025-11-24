<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedPaymentIntent extends CreateRecord
{
    protected static string $resource = ConnectedPaymentIntentResource::class;
}
