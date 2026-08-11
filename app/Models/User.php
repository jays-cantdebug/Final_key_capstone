<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'is_active',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Determine whether the user has one of the given role names.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleNames = is_array($roles) ? $roles : [$roles];

        return $this->role?->name !== null && in_array($this->role->name, $roleNames, true);
    }

    /**
     * Compute display initials (up to two letters) from the user's name.
     */
    public function initials(): string
    {
        $words = array_filter(preg_split('/\s+/', trim($this->name)));

        $letters = array_map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)), $words);

        return implode('', array_slice($letters, 0, 2)) ?: '?';
    }
}
