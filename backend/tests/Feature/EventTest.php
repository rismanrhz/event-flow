<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_unauthenticated_user_cannot_list_events(): void
    {
        $this->getJson('/api/v1/events')->assertStatus(401);
    }

    public function test_participant_cannot_create_event(): void
    {
        Sanctum::actingAs($this->userWithRole('participant'));

        $this->postJson('/api/v1/events', [
            'title' => 'Blocked Event',
            'starts_at' => now()->addMonth()->toISOString(),
        ])->assertStatus(403);
    }

    public function test_owner_can_create_event(): void
    {
        Sanctum::actingAs($this->userWithRole('event_owner'));

        $response = $this->postJson('/api/v1/events', [
            'title' => 'Launch Party',
            'description' => 'Celebrate the product launch.',
            'location' => 'Jakarta',
            'starts_at' => now()->addMonth()->toISOString(),
            'ends_at' => now()->addMonth()->addHours(4)->toISOString(),
            'capacity' => 150,
            'status' => 'published',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.key', 'published')
            ->assertJsonPath('data.slug', 'launch-party');

        $this->assertDatabaseHas('events', ['slug' => 'launch-party', 'status' => 'published']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.created']);
    }

    public function test_owner_can_create_event_with_cover_image(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->post('/api/v1/events', [
            'title' => 'Covered Event',
            'starts_at' => now()->addMonth()->toISOString(),
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ])->assertStatus(201);

        $event = Event::where('title', 'Covered Event')->firstOrFail();
        $this->assertNotNull($event->cover_path);
        Storage::disk('public')->assertExists($event->cover_path);
    }

    public function test_create_event_requires_title_and_starts_at(): void
    {
        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->postJson('/api/v1/events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'starts_at']);
    }

    public function test_create_event_rejects_invalid_status(): void
    {
        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->postJson('/api/v1/events', [
            'title' => 'Invalid Status',
            'starts_at' => now()->addMonth()->toISOString(),
            'status' => 'archived',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_create_event_rejects_ends_at_before_starts_at(): void
    {
        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->postJson('/api/v1/events', [
            'title' => 'Backwards Event',
            'starts_at' => now()->addMonth()->toISOString(),
            'ends_at' => now()->addDay()->toISOString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['ends_at']);
    }

    public function test_unique_slug_is_generated_for_duplicate_titles(): void
    {
        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->postJson('/api/v1/events', ['title' => 'Same Name', 'starts_at' => now()->addMonth()->toISOString()])
            ->assertStatus(201);

        $this->postJson('/api/v1/events', ['title' => 'Same Name', 'starts_at' => now()->addMonth()->toISOString()])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'same-name-2');
    }

    public function test_participant_only_sees_public_events(): void
    {
        $draft = Event::factory()->create(['status' => EventStatus::Draft]);
        Event::factory()->published()->create();
        Event::factory()->completed()->create();

        Sanctum::actingAs($this->userWithRole('participant'));

        $this->getJson('/api/v1/events')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->assertDatabaseHas('events', ['id' => $draft->id, 'status' => 'draft']);
    }

    public function test_owner_sees_their_own_draft_in_list(): void
    {
        $owner = $this->userWithRole('event_owner');
        Event::factory()->create(['created_by' => $owner->id, 'status' => EventStatus::Draft]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/events')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status.key', 'draft');
    }

    public function test_participant_cannot_view_a_draft_event(): void
    {
        $draft = Event::factory()->create(['status' => EventStatus::Draft]);

        Sanctum::actingAs($this->userWithRole('participant'));

        $this->getJson("/api/v1/events/{$draft->id}")->assertStatus(403);
    }

    public function test_manager_can_view_a_draft_event(): void
    {
        $draft = Event::factory()->create(['status' => EventStatus::Draft]);

        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->getJson("/api/v1/events/{$draft->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $draft->id);
    }

    public function test_owner_can_update_their_own_event(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id, 'status' => EventStatus::Draft]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Updated Title',
            'status' => 'published',
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.status.key', 'published');

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.updated', 'event_id' => $event->id]);
    }

    public function test_owner_cannot_update_another_owners_event(): void
    {
        $otherOwner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $otherOwner->id]);

        Sanctum::actingAs($this->userWithRole('event_owner'));

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Hijack'])->assertStatus(403);
    }

    public function test_super_admin_can_update_any_event(): void
    {
        $otherOwner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $otherOwner->id]);

        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Admin Fixed'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Admin Fixed');
    }

    public function test_owner_can_soft_delete_their_own_event(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/events/{$event->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.deleted', 'event_id' => $event->id]);
    }

    public function test_participant_cannot_delete_event(): void
    {
        $event = Event::factory()->published()->create();

        Sanctum::actingAs($this->userWithRole('participant'));

        $this->deleteJson("/api/v1/events/{$event->id}")->assertStatus(403);
    }

    public function test_admin_can_create_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->postJson('/api/v1/events', [
            'title' => 'Admin Event',
            'starts_at' => now()->addMonth()->toISOString(),
        ])->assertStatus(201)->assertJsonPath('data.creator.name', fn ($name) => is_string($name));
    }
}
