<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'name',
        'description',
        'contact_email',
        'contact_phone',
        'active',
        'commission_percent',
        'supplier_ledger_account_number',
        'metadata',
        'archived_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'commission_percent' => 'decimal:2',
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): bool
    {
        if ($this->isArchived()) {
            return true;
        }

        $this->forceFill(['archived_at' => now()])->save();

        return true;
    }

    /**
     * Get the store that owns this vendor
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the products for this vendor
     */
    public function products(): HasMany
    {
        return $this->hasMany(ConnectedProduct::class);
    }
}
