<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Collection;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CollectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Collection');
    }

    public function view(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('View:Collection');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Collection');
    }

    public function update(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('Update:Collection');
    }

    public function delete(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('Delete:Collection');
    }

    public function restore(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('Restore:Collection');
    }

    public function forceDelete(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('ForceDelete:Collection');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Collection');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Collection');
    }

    public function replicate(AuthUser $authUser, Collection $collection): bool
    {
        return $authUser->can('Replicate:Collection');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Collection');
    }
}
