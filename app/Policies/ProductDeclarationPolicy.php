<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductDeclaration;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductDeclarationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductDeclaration');
    }

    public function view(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('View:ProductDeclaration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductDeclaration');
    }

    public function update(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('Update:ProductDeclaration');
    }

    public function delete(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('Delete:ProductDeclaration');
    }

    public function restore(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('Restore:ProductDeclaration');
    }

    public function forceDelete(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('ForceDelete:ProductDeclaration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductDeclaration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductDeclaration');
    }

    public function replicate(AuthUser $authUser, ProductDeclaration $productDeclaration): bool
    {
        return $authUser->can('Replicate:ProductDeclaration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductDeclaration');
    }
}
