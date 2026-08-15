<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_user_list_can_be_searched_by_name_or_email(): void
    {
        $psychometrician = $this->psychometrician();
        User::factory()->create(['name' => 'Unique Findme', 'email' => 'findme@example.test']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.test']);

        $response = $this->actingAs($psychometrician)->get(route('users.index', ['search' => 'Findme']));

        $response->assertOk();
        $response->assertSee('Unique Findme');
        $response->assertDontSee('Someone Else');
    }

    public function test_user_list_normal_request_returns_the_full_page(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('users.index'));

        $response->assertOk();
        $response->assertViewIs('users.index');
    }

    public function test_user_list_live_search_request_returns_only_the_table_partial(): void
    {
        $psychometrician = $this->psychometrician();
        User::factory()->create(['name' => 'Unique Findme', 'email' => 'findme@example.test']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.test']);

        $response = $this->actingAs($psychometrician)
            ->get(route('users.index', ['search' => 'Findme']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('users._table');
        $response->assertSee('Unique Findme');
        $response->assertDontSee('Someone Else');

        // The partial must not carry the surrounding page chrome — only a
        // live-search fetch (not a normal page load) should ever receive
        // this trimmed-down response.
        $response->assertDontSee('User Management');
    }
}
