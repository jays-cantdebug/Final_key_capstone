<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An avatar wider or taller than ProfileUpdateRequest::AVATAR_MAX_PIXELS is
 * refused from its header, before it is decoded (a huge-pixel image could
 * otherwise exhaust PHP's memory and 500). Normal photos are still stored.
 */
class ProfileAvatarDimensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_image_over_the_pixel_limit_is_refused_with_a_clear_message(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $max = ProfileUpdateRequest::AVATAR_MAX_PIXELS;

        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('wide.png', $max + 1, 10),
        ])->assertRedirect('/profile')->assertSessionHasErrors([
            'avatar' => "The photo is too large: it can be at most {$max} × {$max} pixels. Please choose a smaller photo.",
        ]);

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_normal_photo_is_still_accepted_and_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('photo.jpg', 1200, 900),
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_profile_form_states_the_limits(): void
    {
        $max = ProfileUpdateRequest::AVATAR_MAX_PIXELS;

        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertOk()
            ->assertSee("JPG, PNG or WebP, up to 8 MB and {$max} × {$max} pixels.");
    }
}
