<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Leek\FilamentWorkflows\Models\WorkflowSecret as BaseWorkflowSecret;

class WorkflowSecret extends BaseWorkflowSecret
{
    /**
     * Store (tenant) that owns the secret.
     * Required for Filament tenant panel: ownership relationship name is "store".
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, $this->getTenantColumn());
    }
}
