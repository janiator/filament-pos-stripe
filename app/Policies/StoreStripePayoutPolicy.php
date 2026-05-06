<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreStripePayout;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoreStripePayoutPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StoreStripePayout');
    }

    public function view(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('View:StoreStripePayout');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StoreStripePayout');
    }

    public function update(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('Update:StoreStripePayout');
    }

    public function delete(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('Delete:StoreStripePayout');
    }

    public function restore(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('Restore:StoreStripePayout');
    }

    public function forceDelete(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('ForceDelete:StoreStripePayout');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StoreStripePayout');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StoreStripePayout');
    }

    public function replicate(AuthUser $authUser, StoreStripePayout $storeStripePayout): bool
    {
        return $authUser->can('Replicate:StoreStripePayout');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StoreStripePayout');
    }
}
