<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Positiv\FilamentWebflow\Models\WebflowSite;

class WebflowSitePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WebflowSite');
    }

    public function view(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('View:WebflowSite');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WebflowSite');
    }

    public function update(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('Update:WebflowSite');
    }

    public function delete(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('Delete:WebflowSite');
    }

    public function restore(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('Restore:WebflowSite');
    }

    public function forceDelete(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('ForceDelete:WebflowSite');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WebflowSite');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WebflowSite');
    }

    public function replicate(AuthUser $authUser, WebflowSite $webflowSite): bool
    {
        return $authUser->can('Replicate:WebflowSite');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WebflowSite');
    }
}
