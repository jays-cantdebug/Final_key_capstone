<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Injects the current Guidance Counselor's unread notification count into
 * the shared sidebar navigation view, so no query is executed directly
 * inside the Blade template.
 */
class NotificationBadgeComposer
{
    public function __construct(private readonly SystemNotificationService $notificationService) {}

    public function compose(View $view): void
    {
        /** @var (Authenticatable&User)|null $user */
        $user = Auth::user();

        $view->with(
            'unreadNotificationsCount',
            $user !== null && $user->hasRole('guidance_counselor')
                ? $this->notificationService->unreadCountForUser($user)
                : 0
        );
    }
}
