<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Vendor;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Vendor');
    }

    public function view(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('View:Vendor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Vendor');
    }

    public function update(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('Update:Vendor');
    }

    public function delete(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('Delete:Vendor');
    }

    public function restore(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('Restore:Vendor');
    }

    public function forceDelete(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('ForceDelete:Vendor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Vendor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Vendor');
    }

    public function replicate(AuthUser $authUser, Vendor $vendor): bool
    {
        return $authUser->can('Replicate:Vendor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Vendor');
    }

}