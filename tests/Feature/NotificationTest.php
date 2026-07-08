<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_guidance_counselor_only_sees_their_own_notifications(): void
    {
        $counselor = $this->guidanceCounselor();
        $otherCounselor = $this->guidanceCounselor();
        SystemNotification::factory()->create(['user_id' => $counselor->id, 'message' => 'This message belongs to me.']);
        SystemNotification::factory()->create(['user_id' => $otherCounselor->id, 'message' => 'This message belongs to someone else.']);

        $response = $this->actingAs($counselor)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('This message belongs to me.');
        $response->assertDontSee('This message belongs to someone else.');
    }

    public function test_marking_a_notification_as_read_updates_it(): void
    {
        $counselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->create(['user_id' => $counselor->id, 'is_read' => false]);

        $this->actingAs($counselor)->patch(route('notifications.read', $notification))->assertRedirect();

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_a_counselor_cannot_mark_another_counselors_notification_as_read(): void
    {
        $counselor = $this->guidanceCounselor();
        $otherCounselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->create(['user_id' => $otherCounselor->id]);

        $this->actingAs($counselor)->patch(route('notifications.read', $notification))->assertForbidden();
    }

    public function test_psychometrician_has_no_access_to_the_notification_inbox(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('notifications.index'))->assertForbidden();
    }
}
