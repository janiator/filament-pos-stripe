<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ArticleGroupCode;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArticleGroupCodePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ArticleGroupCode');
    }

    public function view(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('View:ArticleGroupCode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ArticleGroupCode');
    }

    public function update(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('Update:ArticleGroupCode');
    }

    public function delete(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('Delete:ArticleGroupCode');
    }

    public function restore(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('Restore:ArticleGroupCode');
    }

    public function forceDelete(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('ForceDelete:ArticleGroupCode');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ArticleGroupCode');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ArticleGroupCode');
    }

    public function replicate(AuthUser $authUser, ArticleGroupCode $articleGroupCode): bool
    {
        return $authUser->can('Replicate:ArticleGroupCode');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ArticleGroupCode');
    }

}