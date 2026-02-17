<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WebhookLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebhookLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WebhookLog');
    }

    public function view(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('View:WebhookLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WebhookLog');
    }

    public function update(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('Update:WebhookLog');
    }

    public function delete(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('Delete:WebhookLog');
    }

    public function restore(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('Restore:WebhookLog');
    }

    public function forceDelete(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('ForceDelete:WebhookLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WebhookLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WebhookLog');
    }

    public function replicate(AuthUser $authUser, WebhookLog $webhookLog): bool
    {
        return $authUser->can('Replicate:WebhookLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WebhookLog');
    }

}