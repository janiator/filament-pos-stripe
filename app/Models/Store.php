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

    /**
     * Get the connected charges for this store
     */
    public function connectedCharges()
    {
        return $this->hasMany(\App\Models\ConnectedCharge::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected transfers for this store
     */
    public function connectedTransfers()
    {
        return $this->hasMany(\App\Models\ConnectedTransfer::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected payment methods for this store
     */
    public function connectedPaymentMethods()
    {
        return $this->hasMany(\App\Models\ConnectedPaymentMethod::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected payment links for this store
     */
    public function connectedPaymentLinks()
    {
        return $this->hasMany(\App\Models\ConnectedPaymentLink::class, 'stripe_account_id', 'stripe_account_id');
    }
}
