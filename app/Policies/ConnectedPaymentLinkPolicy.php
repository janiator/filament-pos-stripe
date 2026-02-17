<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectedPaymentLink;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConnectedPaymentLinkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedPaymentLink');
    }

    public function view(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('View:ConnectedPaymentLink');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedPaymentLink');
    }

    public function update(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('Update:ConnectedPaymentLink');
    }

    public function delete(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('Delete:ConnectedPaymentLink');
    }

    public function restore(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('Restore:ConnectedPaymentLink');
    }

    public function forceDelete(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('ForceDelete:ConnectedPaymentLink');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedPaymentLink');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedPaymentLink');
    }

    public function replicate(AuthUser $authUser, ConnectedPaymentLink $connectedPaymentLink): bool
    {
        return $authUser->can('Replicate:ConnectedPaymentLink');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedPaymentLink');
    }
}
