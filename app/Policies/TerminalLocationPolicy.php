<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TerminalLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerminalLocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TerminalLocation');
    }

    public function view(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('View:TerminalLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TerminalLocation');
    }

    public function update(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('Update:TerminalLocation');
    }

    public function delete(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('Delete:TerminalLocation');
    }

    public function restore(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('Restore:TerminalLocation');
    }

    public function forceDelete(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('ForceDelete:TerminalLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TerminalLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TerminalLocation');
    }

    public function replicate(AuthUser $authUser, TerminalLocation $terminalLocation): bool
    {
        return $authUser->can('Replicate:TerminalLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TerminalLocation');
    }

}