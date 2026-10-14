<?php

namespace Tests\Feature;

use App\Enums\ParticipantStatus;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventParticipant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app('auth')->forgetGuards();
    }

    public function test_user_can_self_register_for_published_event(): void
    {
        $event = Event::factory()->published()->create();
        $participant = $this->userWithRole('participant');

        Sanctum::actingAs($participant, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants")
            ->assertStatus(201)
            ->assertJsonPath('data.status.key', 'registered')
            ->assertJsonPath('data.user.id', $participant->id);

        $this->assertNotEmpty(EventParticipant::query()->where('event_id', $event->id)->first()?->registration_code);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_registered']);
    }

    public function test_user_cannot_register_twice(): void
    {
        $event = Event::factory()->published()->create();
        $participant = $this->userWithRole('participant');
        EventParticipant::factory()->create(['event_id' => $event->id, 'user_id' => $participant->id]);

        Sanctum::actingAs($participant, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants")->assertStatus(422);
    }

    public function test_user_cannot_register_for_draft_event(): void
    {
        $event = Event::factory()->create();
        $participant = $this->userWithRole('participant');

        Sanctum::actingAs($participant, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants")->assertStatus(422);
    }

    public function test_registration_is_waitlisted_when_capacity_full(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['capacity' => 1, 'created_by' => $owner->id]);
        EventParticipant::factory()->registered()->create(['event_id' => $event->id]);
        $second = $this->userWithRole('participant');

        Sanctum::actingAs($second, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants")
            ->assertStatus(201)
            ->assertJsonPath('data.status.key', 'waitlisted');

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_waitlisted']);
    }

    public function test_cancel_promotes_oldest_waitlisted(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['capacity' => 1, 'created_by' => $owner->id]);
        $first = $this->userWithRole('participant');
        $waitlistedA = $this->userWithRole('participant');
        $waitlistedB = $this->userWithRole('participant');

        EventParticipant::factory()->registered()->create(['event_id' => $event->id, 'user_id' => $first->id]);
        EventParticipant::factory()->waitlisted()->create(['event_id' => $event->id, 'user_id' => $waitlistedA->id]);
        EventParticipant::factory()->waitlisted()->create(['event_id' => $event->id, 'user_id' => $waitlistedB->id]);

        Sanctum::actingAs($owner, ['*']);

        $firstRegistration = $event->participants()->where('user_id', $first->id)->firstOrFail();

        $this->postJson("/api/v1/events/{$event->id}/participants/{$firstRegistration->id}/cancel")
            ->assertStatus(200);

        $this->assertDatabaseHas('event_participants', [
            'user_id' => $waitlistedA->id,
            'status' => ParticipantStatus::Registered->value,
        ]);
        $this->assertDatabaseHas('event_participants', [
            'user_id' => $waitlistedB->id,
            'status' => ParticipantStatus::Waitlisted->value,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_promoted']);
    }

    public function test_owner_can_check_in_participant_by_code(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $participant = EventParticipant::factory()->registered()->create(['event_id' => $event->id])->load('user:id,name,email');

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => $participant->registration_code,
        ])->assertStatus(200)
            ->assertJsonPath('data.status.key', 'checked_in');

        $this->assertNotNull($participant->fresh()->checked_in_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_checked_in']);
    }

    public function test_check_in_by_participant_id(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $participant = EventParticipant::factory()->registered()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'participant_id' => $participant->id,
        ])->assertStatus(200)->assertJsonPath('data.status.key', 'checked_in');
    }

    public function test_check_in_with_unknown_code_fails(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => 'NOTREAL123',
        ])->assertStatus(422);
    }

    public function test_waitlisted_and_cancelled_cannot_check_in(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $waitlisted = EventParticipant::factory()->waitlisted()->create(['event_id' => $event->id]);
        $cancelled = EventParticipant::factory()->cancelled()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => $waitlisted->registration_code,
        ])->assertStatus(422);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => $cancelled->registration_code,
        ])->assertStatus(422);
    }

    public function test_checking_in_already_checked_in_is_noop(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $participant = EventParticipant::factory()->checkedIn()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => $participant->registration_code,
        ])->assertStatus(200)->assertJsonPath('data.status.key', 'checked_in');
    }

    public function test_owner_can_add_participant(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $invitee = $this->userWithRole('participant');

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/participants", [
            'user_id' => $invitee->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.status.key', 'registered')
            ->assertJsonPath('data.user.id', $invitee->id);

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_added']);
    }

    public function test_participant_cannot_manage_others(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $participant = $this->userWithRole('participant');

        Sanctum::actingAs($participant, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants")->assertStatus(403);
        $this->postJson("/api/v1/events/{$event->id}/participants", ['user_id' => $owner->id])->assertStatus(403);
    }

    public function test_member_with_manage_participants_can_view(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $member = EventMember::factory()->withPermissions(['manage_participants'])->create(['event_id' => $event->id]);
        EventParticipant::factory()->registered()->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user()->first(), ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_member_with_manage_attendance_can_view_and_check_in(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $member = EventMember::factory()->withPermissions(['manage_attendance'])->create(['event_id' => $event->id]);
        $participant = EventParticipant::factory()->registered()->create(['event_id' => $event->id]);

        $user = $member->user()->first();
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants")->assertStatus(200);
        $this->postJson("/api/v1/events/{$event->id}/participants/check-in", [
            'code' => $participant->registration_code,
        ])->assertStatus(200)->assertJsonPath('data.status.key', 'checked_in');
    }

    public function test_member_without_participant_perms_cannot_view(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $member = EventMember::factory()->withPermissions(['manage_tasks'])->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user()->first(), ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants")->assertStatus(403);
    }

    public function test_mine_returns_own_registration_only(): void
    {
        $event = Event::factory()->published()->create();
        $participant = $this->userWithRole('participant');
        $registration = EventParticipant::factory()->registered()->create(['event_id' => $event->id, 'user_id' => $participant->id]);

        Sanctum::actingAs($participant, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants/mine")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $registration->id)
            ->assertJsonPath('data.registration_code', $registration->registration_code);

        Sanctum::actingAs($this->userWithRole('participant'), ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants/mine")->assertStatus(404);
    }

    public function test_owner_can_remove_participant(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);
        $participant = EventParticipant::factory()->registered()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->deleteJson("/api/v1/events/{$event->id}/participants/{$participant->id}")->assertStatus(200);
        $this->assertSoftDeleted('event_participants', ['id' => $participant->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.participant_removed']);
    }

    public function test_stats_returns_summary(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['capacity' => 50, 'created_by' => $owner->id]);
        EventParticipant::factory()->registered()->create(['event_id' => $event->id]);
        EventParticipant::factory()->checkedIn()->create(['event_id' => $event->id]);
        EventParticipant::factory()->waitlisted()->create(['event_id' => $event->id]);
        EventParticipant::factory()->cancelled()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/participants/stats")
            ->assertStatus(200)
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.checked_in', 1)
            ->assertJsonPath('data.waitlisted', 1)
            ->assertJsonPath('data.cancelled', 1)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.capacity', 50)
            ->assertJsonPath('data.remaining', 48);
    }
}
