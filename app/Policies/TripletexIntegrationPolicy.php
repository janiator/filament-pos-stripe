<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TripletexIntegration;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TripletexIntegrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TripletexIntegration');
    }

    public function view(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('View:TripletexIntegration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TripletexIntegration');
    }

    public function update(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('Update:TripletexIntegration');
    }

    public function delete(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('Delete:TripletexIntegration');
    }

    public function restore(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('Restore:TripletexIntegration');
    }

    public function forceDelete(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('ForceDelete:TripletexIntegration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TripletexIntegration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TripletexIntegration');
    }

    public function replicate(AuthUser $authUser, TripletexIntegration $tripletexIntegration): bool
    {
        return $authUser->can('Replicate:TripletexIntegration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TripletexIntegration');
    }
}
