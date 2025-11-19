<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedCustomer extends CreateRecord
{
    protected static string $resource = ConnectedCustomerResource::class;
}
