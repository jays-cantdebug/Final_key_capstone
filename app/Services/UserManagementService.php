<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserManagementGuardException;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ActiveSessionGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Manages Psychometrician and Guidance Counselor user accounts: creation,
 * editing, activation/deactivation, and password resets, enforcing the
 * Account Recovery Safety Net.
 */
class UserManagementService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ActiveSessionGuard $activeSessionGuard,
        private readonly RemoteAssessmentService $remoteAssessments,
    ) {}

    /**
     * Paginate users, optionally searched by name or email.
     */
    public function paginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->when($search, function ($query, string $value) {
                $query->where(function ($q) use ($value) {
                    $q->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return $this->database->transaction(function () use ($data): User {
            return User::query()->create([
                ...$data,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
        });
    }

    /**
     * Update a user's name, email, and role.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws UserManagementGuardException if this would demote the last
     *                                      remaining active Psychometrician.
     */
    public function update(User $user, array $data): User
    {
        if ($this->wouldDemoteLastActivePsychometrician($user, (int) $data['role_id'])) {
            throw new UserManagementGuardException(
                'Cannot change the role of the last remaining active Psychometrician account.'
            );
        }

        return $this->database->transaction(function () use ($user, $data): User {
            $user->update($data);

            return $user->refresh();
        });
    }

    public function activate(User $user): User
    {
        return $this->database->transaction(function () use ($user): User {
            $user->update(['is_active' => true]);

            return $user->refresh();
        });
    }

    /**
     * Deactivating ends access at once, not only at the next login: the
     * user's session rows are deleted and their remember-me token is
     * replaced, so neither an open session nor a remember-me cookie gets
     * them back in. (EnsureUserIsActive is the per-request safety net for
     * any session this can't reach, e.g. a non-database session driver.)
     */
    public function deactivate(User $user): User
    {
        return $this->database->transaction(function () use ($user): User {
            // One save, so the audit log still records a single Update
            // (remember_token is never written to audit_logs).
            $user->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                $this->activeSessionGuard->forceLogout($user);
            }

            // A deactivated Psychometrician's student-device draft ends too.
            $this->remoteAssessments->discardFor($user);

            return $user->refresh();
        });
    }

    /**
     * End every active session this user has, anywhere — the recovery
     * path for single-session enforcement when a locked-out user can't
     * wait for their prior session to expire on its own.
     */
    public function forceLogout(User $user): void
    {
        $this->activeSessionGuard->forceLogout($user);
        // No Logout event fires for sessions ended this way, so their
        // student-device draft is discarded here.
        $this->remoteAssessments->discardFor($user);
    }

    public function resetPassword(User $user, string $password): User
    {
        return $this->database->transaction(function () use ($user, $password): User {
            $user->update(['password' => Hash::make($password)]);

            return $user->refresh();
        });
    }

    public function activePsychometricianCount(): int
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'psychometrician'))
            ->where('is_active', true)
            ->count();
    }

    private function wouldDemoteLastActivePsychometrician(User $user, int $newRoleId): bool
    {
        if (! $user->hasRole('psychometrician') || ! $user->is_active) {
            return false;
        }

        $psychometricianRoleId = Role::query()->where('name', 'psychometrician')->value('id');

        if ($newRoleId === $psychometricianRoleId) {
            return false;
        }

        return $this->activePsychometricianCount() <= 1;
    }
}
