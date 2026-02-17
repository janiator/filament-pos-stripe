<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectedPaymentIntent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConnectedPaymentIntentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedPaymentIntent');
    }

    public function view(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('View:ConnectedPaymentIntent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedPaymentIntent');
    }

    public function update(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('Update:ConnectedPaymentIntent');
    }

    public function delete(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('Delete:ConnectedPaymentIntent');
    }

    public function restore(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('Restore:ConnectedPaymentIntent');
    }

    public function forceDelete(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('ForceDelete:ConnectedPaymentIntent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedPaymentIntent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedPaymentIntent');
    }

    public function replicate(AuthUser $authUser, ConnectedPaymentIntent $connectedPaymentIntent): bool
    {
        return $authUser->can('Replicate:ConnectedPaymentIntent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedPaymentIntent');
    }
}
