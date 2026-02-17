<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EventTicket;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventTicketPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EventTicket');
    }

    public function view(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('View:EventTicket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EventTicket');
    }

    public function update(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('Update:EventTicket');
    }

    public function delete(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('Delete:EventTicket');
    }

    public function restore(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('Restore:EventTicket');
    }

    public function forceDelete(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('ForceDelete:EventTicket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EventTicket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EventTicket');
    }

    public function replicate(AuthUser $authUser, EventTicket $eventTicket): bool
    {
        return $authUser->can('Replicate:EventTicket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EventTicket');
    }

}