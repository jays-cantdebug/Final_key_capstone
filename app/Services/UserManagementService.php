<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserManagementGuardException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;

/**
 * Manages Psychometrician and Guidance Counselor user accounts: creation,
 * editing, activation/deactivation, and password resets, enforcing the
 * Account Recovery Safety Net.
 */
class UserManagementService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

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
     * @param array<string, mixed> $data
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
     * @param array<string, mixed> $data
     *
     * @throws UserManagementGuardException if this would demote the last
     *                                       remaining active Psychometrician.
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

    public function deactivate(User $user): User
    {
        return $this->database->transaction(function () use ($user): User {
            $user->update(['is_active' => false]);

            return $user->refresh();
        });
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
