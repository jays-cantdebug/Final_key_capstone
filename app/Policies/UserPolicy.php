<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any user accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can view the given user account.
     */
    public function view(User $user, User $target): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can create user accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can edit the given user account's
     * name, email, and role. The "demoting the last active
     * Psychometrician" guard is enforced separately in
     * UserManagementService, since it depends on the incoming role
     * value rather than the target's current state.
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can reset the given user account's
     * password.
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can activate the given user account.
     */
    public function activate(User $user, User $target): bool
    {
        return $user->hasRole('psychometrician');
    }

    /**
     * Determine whether the user can deactivate the given user account.
     *
     * Enforces the Account Recovery Safety Net: a user cannot deactivate
     * their own account, and the last remaining active Psychometrician
     * account cannot be deactivated by anyone.
     */
    public function deactivate(User $user, User $target): Response
    {
        if (! $user->hasRole('psychometrician')) {
            return Response::deny('You are not authorized to manage user accounts.');
        }

        if ($user->id === $target->id) {
            return Response::deny('You cannot deactivate your own account.');
        }

        if ($target->hasRole('psychometrician') && $target->is_active && $this->activePsychometricianCount() <= 1) {
            return Response::deny('Cannot deactivate the last remaining active Psychometrician account.');
        }

        return Response::allow();
    }

    private function activePsychometricianCount(): int
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'psychometrician'))
            ->where('is_active', true)
            ->count();
    }
}
