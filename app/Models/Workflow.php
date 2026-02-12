<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Leek\FilamentWorkflows\Models\Workflow as BaseWorkflow;

class Workflow extends BaseWorkflow
{
    /**
     * Store (tenant) that owns the workflow.
     * Required for Filament tenant panel: ownership relationship name is "store".
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, $this->getTenantColumn());
    }
}
