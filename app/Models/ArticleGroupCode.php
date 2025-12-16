<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleGroupCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'code',
        'name',
        'description',
        'default_vat_percent',
        'is_standard',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'default_vat_percent' => 'decimal:2',
        'is_standard' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the store that owns this article group code
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the products using this article group code
     * Note: Uses the code string, not a foreign key
     */
    public function products(): HasMany
    {
        return $this->hasMany(ConnectedProduct::class, 'article_group_code', 'code');
    }
}
