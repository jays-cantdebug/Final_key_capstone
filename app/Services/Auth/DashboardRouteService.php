<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

class DashboardRouteService
{
    /**
     * Resolve the dashboard route name for the current user.
     */
    public function resolve(User $user): string
    {
        return match ($user->role?->name) {
            'psychometrician' => 'psychometrician.dashboard',
            'guidance_counselor' => 'guidance-counselor.dashboard',
            default => 'dashboard',
        };
    }
}