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

    public function test_archiving_a_notification_hides_it_from_the_default_inbox_but_keeps_the_row(): void
    {
        $counselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->create(['user_id' => $counselor->id, 'message' => 'Archive me.']);

        $this->actingAs($counselor)->patch(route('notifications.archive', $notification))->assertRedirect();

        $this->assertNotNull($notification->fresh()->archived_at);

        $response = $this->actingAs($counselor)->get(route('notifications.index'));
        $response->assertOk();
        $response->assertDontSee('Archive me.');

        $this->assertDatabaseHas('system_notifications', ['id' => $notification->id]);
    }

    public function test_archived_notifications_are_visible_via_the_view_archived_toggle(): void
    {
        $counselor = $this->guidanceCounselor();
        $archived = SystemNotification::factory()->archived()->create(['user_id' => $counselor->id, 'message' => 'I am archived.']);
        $active = SystemNotification::factory()->create(['user_id' => $counselor->id, 'message' => 'I am active.']);

        $defaultView = $this->actingAs($counselor)->get(route('notifications.index'));
        $defaultView->assertOk();
        $defaultView->assertSee('I am active.');
        $defaultView->assertDontSee('I am archived.');

        $archivedView = $this->actingAs($counselor)->get(route('notifications.index', ['archived' => 1]));
        $archivedView->assertOk();
        $archivedView->assertSee('I am archived.');
        $archivedView->assertDontSee('I am active.');
    }

    public function test_a_counselor_cannot_archive_another_counselors_notification(): void
    {
        $counselor = $this->guidanceCounselor();
        $otherCounselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->create(['user_id' => $otherCounselor->id]);

        $this->actingAs($counselor)->patch(route('notifications.archive', $notification))->assertForbidden();
    }

    public function test_archiving_an_already_archived_notification_does_not_change_its_archived_at(): void
    {
        $counselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->archived()->create(['user_id' => $counselor->id]);
        $originalArchivedAt = $notification->archived_at;

        $this->actingAs($counselor)->patch(route('notifications.archive', $notification))->assertRedirect();

        $this->assertTrue($notification->fresh()->archived_at->equalTo($originalArchivedAt));
    }

    public function test_unarchiving_a_notification_restores_it_to_the_default_inbox(): void
    {
        $counselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->archived()->create(['user_id' => $counselor->id, 'message' => 'Bring me back.']);

        $this->actingAs($counselor)->patch(route('notifications.unarchive', $notification))->assertRedirect();

        $this->assertNull($notification->fresh()->archived_at);

        $response = $this->actingAs($counselor)->get(route('notifications.index'));
        $response->assertOk();
        $response->assertSee('Bring me back.');
    }

    public function test_a_counselor_cannot_unarchive_another_counselors_notification(): void
    {
        $counselor = $this->guidanceCounselor();
        $otherCounselor = $this->guidanceCounselor();
        $notification = SystemNotification::factory()->archived()->create(['user_id' => $otherCounselor->id]);

        $this->actingAs($counselor)->patch(route('notifications.unarchive', $notification))->assertForbidden();
    }
}
