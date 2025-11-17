<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable as CashierBillable;
use Lanos\CashierConnect\Billable as ConnectBillable;
use Lanos\CashierConnect\Contracts\StripeAccount;

class Store extends Model implements StripeAccount
{
    use HasFactory;
    use CashierBillable;
    use ConnectBillable;

    protected $fillable = [
        'name',
        'email',
        'commission_type',
        'commission_rate',
        'stripe_account_id',
    ];

    protected $casts = [
        'commission_rate' => 'integer',
    ];

    public function terminalLocations()
    {
        return $this->hasMany(\App\Models\TerminalLocation::class);
    }

    public function terminalReaders()
    {
        return $this->hasMany(\App\Models\TerminalReader::class);
    }

}
