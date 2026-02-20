<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GiftCard;
use Illuminate\Auth\Access\HandlesAuthorization;

class GiftCardPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GiftCard');
    }

    public function view(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('View:GiftCard');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GiftCard');
    }

    public function update(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('Update:GiftCard');
    }

    public function delete(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('Delete:GiftCard');
    }

    public function restore(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('Restore:GiftCard');
    }

    public function forceDelete(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('ForceDelete:GiftCard');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GiftCard');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GiftCard');
    }

    public function replicate(AuthUser $authUser, GiftCard $giftCard): bool
    {
        return $authUser->can('Replicate:GiftCard');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GiftCard');
    }

}