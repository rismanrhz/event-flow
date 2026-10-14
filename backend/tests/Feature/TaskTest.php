<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Task;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_can_create_task(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/tasks", [
            'title' => 'Book speakers',
            'description' => 'Confirm availability.',
            'priority' => 'high',
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])->assertStatus(201)
            ->assertJsonPath('data.title', 'Book speakers')
            ->assertJsonPath('data.status.key', 'todo')
            ->assertJsonPath('data.priority.key', 'high');

        $this->assertDatabaseHas('event_tasks', ['event_id' => $event->id, 'title' => 'Book speakers']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.task_created']);
    }

    public function test_member_with_manage_tasks_can_create_task(): void
    {
        $event = Event::factory()->published()->create();
        $member = EventMember::factory()
            ->withPermissions(['manage_tasks'])
            ->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/tasks", [
            'title' => 'Design banners',
            'assignee_id' => $member->user_id,
        ])->assertStatus(201)
            ->assertJsonPath('data.assignee.id', $member->user_id);
    }

    public function test_member_without_manage_tasks_cannot_view_tasks(): void
    {
        $event = Event::factory()->published()->create();
        $member = EventMember::factory()->create(['event_id' => $event->id]);
        Task::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($member->user, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/tasks")->assertStatus(403);
    }

    public function test_participant_cannot_view_tasks(): void
    {
        $event = Event::factory()->published()->create();
        Task::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($this->userWithRole('participant'), ['*']);

        $this->getJson("/api/v1/events/{$event->id}/tasks")->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $event = Event::factory()->create();

        $this->getJson("/api/v1/events/{$event->id}/tasks")->assertStatus(401);
    }

    public function test_task_list_can_be_filtered_by_status(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        Task::factory()->create(['event_id' => $event->id, 'title' => 'First todo', 'status' => TaskStatus::Todo]);
        Task::factory()->create(['event_id' => $event->id, 'title' => 'In progress', 'status' => TaskStatus::InProgress]);

        Sanctum::actingAs($owner, ['*']);

        $this->getJson("/api/v1/events/{$event->id}/tasks?filter[status]=todo")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'First todo');
    }

    public function test_owner_can_update_task_assignee_and_status(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $assignee = $this->userWithRole('committee_member');
        EventMember::factory()->create(['event_id' => $event->id, 'user_id' => $assignee->id]);
        $task = Task::factory()->create(['event_id' => $event->id, 'status' => TaskStatus::Todo]);

        Sanctum::actingAs($owner, ['*']);

        $this->putJson("/api/v1/events/{$event->id}/tasks/{$task->id}", [
            'status' => TaskStatus::InProgress,
            'assignee_id' => $assignee->id,
            'priority' => TaskPriority::High,
        ])->assertStatus(200)
            ->assertJsonPath('data.status.key', 'in_progress')
            ->assertJsonPath('data.priority.key', 'high')
            ->assertJsonPath('data.assignee.id', $assignee->id);

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.task_updated']);
    }

    public function test_creating_task_with_non_member_assignee_is_rejected(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $outsider = $this->userWithRole('committee_member');

        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/events/{$event->id}/tasks", [
            'title' => 'Invalid assignee',
            'assignee_id' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_owner_can_delete_task(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $task = Task::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->deleteJson("/api/v1/events/{$event->id}/tasks/{$task->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('event_tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.task_deleted']);
    }

    public function test_super_admin_can_manage_tasks_of_any_event(): void
    {
        $otherOwner = $this->userWithRole('event_owner');
        $event = Event::factory()->create(['created_by' => $otherOwner->id]);

        Sanctum::actingAs($this->userWithRole('super_admin'), ['*']);

        $this->postJson("/api/v1/events/{$event->id}/tasks", [
            'title' => 'Admin oversight task',
        ])->assertStatus(201);
    }

    public function test_task_from_another_event_returns_404(): void
    {
        $owner = $this->userWithRole('event_owner');
        $eventA = Event::factory()->create(['created_by' => $owner->id]);
        $eventB = Event::factory()->create(['created_by' => $owner->id]);
        $taskOnB = Task::factory()->create(['event_id' => $eventB->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->putJson("/api/v1/events/{$eventA->id}/tasks/{$taskOnB->id}", ['status' => TaskStatus::Done])
            ->assertStatus(404);

        $this->getJson("/api/v1/events/{$eventA->id}/tasks/{$taskOnB->id}")->assertStatus(404);
    }
}
