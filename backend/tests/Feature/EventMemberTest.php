<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_can_add_committee_member_with_permissions(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $budi = $this->userWithRole('committee_member');

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/members", [
            'user_id' => $budi->id,
            'role_title' => 'Secretary',
            'permissions' => ['manage_tasks', 'manage_schedule'],
        ])->assertStatus(201)
            ->assertJsonPath('data.user.name', $budi->name)
            ->assertJsonPath('data.role_title', 'Secretary')
            ->assertJsonCount(2, 'data.permissions');

        $this->assertDatabaseHas('event_members', ['event_id' => $event->id, 'user_id' => $budi->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.member_added']);
    }

    public function test_cannot_add_participant_as_member(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $participant = $this->userWithRole('participant');

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/members", [
            'user_id' => $participant->id,
        ])->assertStatus(422);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $budi = $this->userWithRole('committee_member');

        EventMember::factory()->create(['event_id' => $event->id, 'user_id' => $budi->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/members", [
            'user_id' => $budi->id,
        ])->assertStatus(422);
    }

    public function test_participant_cannot_add_member(): void
    {
        $event = Event::factory()->published()->create();
        $budi = $this->userWithRole('committee_member');

        Sanctum::actingAs($this->userWithRole('participant'));

        $this->postJson("/api/v1/events/{$event->id}/members", ['user_id' => $budi->id])
            ->assertStatus(403);
    }

    public function test_participant_cannot_view_members(): void
    {
        $event = Event::factory()->published()->create();

        Sanctum::actingAs($this->userWithRole('participant'));

        $this->getJson("/api/v1/events/{$event->id}/members")->assertStatus(403);
    }

    public function test_committee_member_can_view_members_of_their_event(): void
    {
        $event = Event::factory()->published()->create();
        $member = EventMember::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user);

        $this->getJson("/api/v1/events/{$event->id}/members")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_can_update_member_permissions(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $member = EventMember::factory()
            ->withPermissions(['manage_tasks'])
            ->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->putJson("/api/v1/events/{$event->id}/members/{$member->id}", [
            'role_title' => 'Treasurer',
            'permissions' => ['manage_budget'],
        ])->assertStatus(200)
            ->assertJsonPath('data.role_title', 'Treasurer')
            ->assertJsonPath('data.permissions', ['manage_budget']);
    }

    public function test_member_without_edit_permission_cannot_manage_members(): void
    {
        $event = Event::factory()->published()->create();
        $member = EventMember::factory()->withPermissions(['manage_tasks'])->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user);

        $this->postJson("/api/v1/events/{$event->id}/members", [
            'user_id' => $this->userWithRole('committee_member')->id,
        ])->assertStatus(403);
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $member = EventMember::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->deleteJson("/api/v1/events/{$event->id}/members/{$member->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('event_members', ['id' => $member->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.member_removed']);
    }

    public function test_candidates_excludes_existing_members_and_participants(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);

        $existing = $this->userWithRole('committee_member');
        EventMember::factory()->create(['event_id' => $event->id, 'user_id' => $existing->id]);

        $available = $this->userWithRole('committee_member');
        $participant = $this->userWithRole('participant');
        $this->userWithRole('event_owner');

        Sanctum::actingAs($owner, ['*']);

        $response = $this->getJson("/api/v1/events/{$event->id}/member-candidates");
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($existing->id, $ids->all());
        $this->assertNotContains($participant->id, $ids->all());
        $this->assertContains($available->id, $ids->all());
    }

    public function test_super_admin_can_manage_members_of_any_event(): void
    {
        $otherOwner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $otherOwner->id]);
        $budi = $this->userWithRole('committee_member');

        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/events/{$event->id}/members", ['user_id' => $budi->id])
            ->assertStatus(201);
    }

    public function test_member_with_edit_permission_can_update_event(): void
    {
        $event = Event::factory()->published()->create();
        EventMember::factory()->withPermissions(['edit_event'])->create(['event_id' => $event->id]);
        $member = $event->eventMembers()->first();

        Sanctum::actingAs($member->user);

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Edited by Committee'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Edited by Committee');
    }
}
