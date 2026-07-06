<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CounselingSession;
use App\Models\User;

class CounselingSessionPolicy
{
    /**
     * Determine whether the user can view any counseling sessions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('guidance_counselor');
    }

    /**
     * Determine whether the user can view a specific counseling session.
     *
     * Viewing the session record itself is unrestricted for any Guidance
     * Counselor; the session_notes field is separately redacted for
     * Restricted sessions the viewer did not create (see
     * CounselingSession::isRestrictedFor()).
     */
    public function view(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('guidance_counselor');
    }

    /**
     * Determine whether the user can create counseling sessions.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('guidance_counselor');
    }

    /**
     * Determine whether the user can update the session.
     *
     * A Restricted session may only be modified by the counselor who
     * created it, since a non-creator cannot safely edit notes they are
     * not permitted to read.
     */
    public function update(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('guidance_counselor') && ! $session->isRestrictedFor($user);
    }

    /**
     * Determine whether the user can delete the session.
     */
    public function delete(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('guidance_counselor') && ! $session->isRestrictedFor($user);
    }
}
