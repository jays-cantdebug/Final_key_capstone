<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AvatarService $avatarService) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->safe()->except(['avatar', 'remove_avatar']);

        $user->fill($validated);

        if ($request->boolean('remove_avatar')) {
            $this->avatarService->delete($user->avatar_path);
            $user->avatar_path = null;
        } elseif ($request->hasFile('avatar')) {
            $this->avatarService->delete($user->avatar_path);
            $user->avatar_path = $this->avatarService->store($request->file('avatar'));
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Profile information updated successfully.');
    }
}
