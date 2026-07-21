<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_psychometrician_dashboard_renders(): void
    {
        $this->actingAs($this->psychometrician())
            ->get(route('psychometrician.dashboard'))
            ->assertOk();
    }

    public function test_guidance_counselor_dashboard_renders(): void
    {
        $this->actingAs($this->guidanceCounselor())
            ->get(route('guidance-counselor.dashboard'))
            ->assertOk();
    }

    public function test_generic_dashboard_route_redirects_to_the_role_specific_dashboard(): void
    {
        $this->actingAs($this->psychometrician())
            ->get(route('dashboard'))
            ->assertRedirect(route('psychometrician.dashboard'));

        $this->actingAs($this->guidanceCounselor())
            ->get(route('dashboard'))
            ->assertRedirect(route('guidance-counselor.dashboard'));
    }
}
