<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\UserManagementGuardException;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\UserFormRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $userService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->get('search');
        $users = $this->userService->paginate($search);

        if ($request->header('X-Live-Search') === 'true') {
            return view('users._table', [
                'users' => $users,
                'search' => $search,
            ]);
        }

        return view('users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['roles' => Role::all()]);
    }

    public function store(UserFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $user = $this->userService->create($request->validated());

        return redirect()->route('users.show', $user)->with('status', 'User account created successfully.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        $user->load('role');

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['user' => $user, 'roles' => Role::all()]);
    }

    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        try {
            $this->userService->update($user, $request->validated());
        } catch (UserManagementGuardException $exception) {
            return back()->withErrors(['role_id' => $exception->getMessage()]);
        }

        return redirect()->route('users.show', $user)->with('status', 'User account updated successfully.');
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);

        $this->userService->activate($user);

        return back()->with('status', 'User account activated successfully.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        try {
            Gate::authorize('deactivate', $user);
        } catch (AuthorizationException $exception) {
            return back()->withErrors(['deactivate' => $exception->getMessage()]);
        }

        $this->userService->deactivate($user);

        return back()->with('status', 'User account deactivated successfully.');
    }

    public function forceLogout(User $user): RedirectResponse
    {
        Gate::authorize('forceLogout', $user);

        $this->userService->forceLogout($user);

        return back()->with('status', 'All active sessions for this account have been ended.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $this->userService->resetPassword($user, $request->validated('password'));

        return back()->with('status', 'Password reset successfully.');
    }
}
