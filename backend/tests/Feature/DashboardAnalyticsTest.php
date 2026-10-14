<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventParticipant;
use App\Models\Task;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app('auth')->forgetGuards();
    }

    public function test_owner_gets_full_command_center_for_their_event(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
            'capacity' => 100,
        ]);

        EventParticipant::factory()->count(10)->registered()->create([
            'event_id' => $event->id,
            'created_at' => now()->subDays(40),
        ]);
        EventParticipant::factory()->count(15)->registered()->create([
            'event_id' => $event->id,
            'created_at' => now()->subDays(20),
        ]);
        EventParticipant::factory()->count(5)->checkedIn()->create([
            'event_id' => $event->id,
            'created_at' => now()->subDays(20),
            'checked_in_at' => now()->subDays(8),
        ]);

        Task::factory()->count(3)->done()->create([
            'event_id' => $event->id,
            'updated_at' => now()->subDays(5),
        ]);
        Task::factory()->count(2)->inProgress()->create([
            'event_id' => $event->id,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->create([
            'event_id' => $event->id,
            'due_at' => now()->addDays(5),
        ]);

        ActivityLog::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'action' => 'event.updated',
            'module' => 'event',
            'description' => 'Event details updated.',
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($owner, ['*']);

        $response = $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'organizer')
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.event.capacity', 100);

        $response
            ->assertJsonPath('data.kpi.activity.current', 20)
            ->assertJsonPath('data.kpi.activity.previous', 10)
            ->assertJsonPath('data.kpi.activity.delta', 100)
            ->assertJsonPath('data.kpi.attendance.current', 25)
            ->assertJsonPath('data.kpi.attendance.applicable', true)
            ->assertJsonPath('data.kpi.task_completion.current', 3)
            ->assertJsonPath('data.kpi.task_completion.delta', 100)
            ->assertJsonPath('data.kpi.overdue.value', 2)
            ->assertJsonPath('data.kpi.capacity.value', 30)
            ->assertJsonPath('data.kpi.capacity.remaining', 70)
            ->assertJsonPath('data.kpi.budget', null);

        $response
            ->assertJsonPath('data.attendance.registered', 25)
            ->assertJsonPath('data.attendance.checked_in', 5)
            ->assertJsonPath('data.attendance.rate', 17)
            ->assertJsonPath('data.attendance.waitlisted', 0)
            ->assertJsonPath('data.funnel.registered', 30)
            ->assertJsonPath('data.funnel.checked_in', 5)
            ->assertJsonPath('data.funnel.no_show', 25)
            ->assertJsonPath('data.tasks.total', 6)
            ->assertJsonPath('data.tasks.done', 3)
            ->assertJsonPath('data.tasks.overdue', 2)
            ->assertJsonPath('data.tasks.completion', 50);
        $this->assertCount(12, $response->json('data.attendance.hours.values'));
        $this->assertCount(3, $response->json('data.tasks.by_status'));
        $this->assertCount(3, $response->json('data.tasks.by_priority'));

        $trend = $response->json('data.trend');
        $this->assertCount(30, $trend['labels']);
        $this->assertCount(30, $trend['registered']);
        $this->assertCount(30, $trend['checked_in']);
        $this->assertCount(30, $trend['cumulative']);
        $this->assertSame(20, array_sum($trend['registered']));
        $this->assertSame(5, array_sum($trend['checked_in']));

        $health = $response->json('data.health');
        $this->assertGreaterThanOrEqual(0, $health['score']);
        $this->assertLessThanOrEqual(100, $health['score']);
        $this->assertCount(4, $health['factors']);
        $this->assertContains($health['grade'], ['Healthy', 'At risk', 'Critical']);

        $insights = $response->json('data.insights');
        $this->assertNotEmpty($insights);
        $this->assertContains($insights[0]['severity'], ['critical', 'warning']);
        $this->assertNotEmpty($response->json('data.attention'));

        $this->assertNotEmpty($response->json('data.schedule'));
        $this->assertNotEmpty($response->json('data.activity'));
        $this->assertSame('event.updated', $response->json('data.activity.0.action'));
    }

    public function test_owner_can_select_a_specific_event(): void
    {
        $owner = $this->userWithRole('event_owner');
        Event::factory()->published()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $second = Event::factory()->published()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addWeeks(4),
        ]);

        Sanctum::actingAs($owner, ['*']);

        $this->getJson('/api/v1/dashboard/overview?event_id='.$second->id)
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'organizer')
            ->assertJsonPath('data.event.id', $second->id);
    }

    public function test_participant_gets_minimal_dashboard_with_public_events_and_tickets(): void
    {
        $participant = $this->userWithRole('participant');
        $published = Event::factory()->published()->create();
        Event::factory()->create(); // draft event stays hidden
        EventParticipant::factory()->registered()->create([
            'event_id' => $published->id,
            'user_id' => $participant->id,
        ]);

        Sanctum::actingAs($participant, ['*']);

        $response = $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'participant');

        $this->assertCount(1, $response->json('data.events'));
        $this->assertSame($published->id, $response->json('data.events.0.value'));
        $this->assertCount(1, $response->json('data.my_tickets'));
        $this->assertSame($published->id, $response->json('data.my_tickets.0.event.id'));
        $this->assertArrayNotHasKey('kpi', $response->json('data'));
    }

    public function test_participant_tickets_to_deleted_events_are_hidden(): void
    {
        $participant = $this->userWithRole('participant');
        $live = Event::factory()->published()->create();
        $deleted = Event::factory()->published()->create();

        EventParticipant::factory()->registered()->create([
            'event_id' => $live->id,
            'user_id' => $participant->id,
        ]);
        EventParticipant::factory()->registered()->create([
            'event_id' => $deleted->id,
            'user_id' => $participant->id,
        ]);

        $deleted->delete();

        Sanctum::actingAs($participant, ['*']);

        $response = $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'participant');

        $this->assertCount(1, $response->json('data.my_tickets'));
        $this->assertSame($live->id, $response->json('data.my_tickets.0.event.id'));
    }

    public function test_committee_member_with_task_permission_sees_only_task_sections(): void
    {
        $committee = $this->userWithRole('committee_member');
        $event = Event::factory()->published()->create();
        EventMember::factory()->withPermissions(['manage_tasks'])->create([
            'event_id' => $event->id,
            'user_id' => $committee->id,
        ]);

        EventParticipant::factory()->count(4)->registered()->create(['event_id' => $event->id]);
        Task::factory()->count(2)->done()->create([
            'event_id' => $event->id,
            'updated_at' => now()->subDays(2),
        ]);
        Task::factory()->create(['event_id' => $event->id]);

        Sanctum::actingAs($committee, ['*']);

        $response = $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'organizer')
            ->assertJsonPath('data.event.id', $event->id);

        $response
            ->assertJsonPath('data.trend', null)
            ->assertJsonPath('data.attendance', null)
            ->assertJsonPath('data.funnel', null)
            ->assertJsonPath('data.kpi.activity', null)
            ->assertJsonPath('data.kpi.attendance', null)
            ->assertJsonPath('data.kpi.capacity', null)
            ->assertJsonPath('data.tasks.completion', 67)
            ->assertJsonPath('data.kpi.task_completion.current', 2);

        $labels = array_column($response->json('data.insights'), 'severity');
        $this->assertNotContains('critical', $labels);
        $this->assertNotContains('warning', $labels);
    }

    public function test_committee_member_without_event_permissions_gets_participant_mode(): void
    {
        $committee = $this->userWithRole('committee_member');
        $event = Event::factory()->published()->create();
        EventMember::factory()->create([
            'event_id' => $event->id,
            'user_id' => $committee->id,
        ]);

        Sanctum::actingAs($committee, ['*']);

        $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'participant');
    }

    public function test_analytics_endpoint_returns_full_sections_and_respects_range(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);

        $universityA = $this->createInstitutionalUser('Univ A');
        $universityB = $this->createInstitutionalUser('Univ B');
        EventParticipant::factory()->checkedIn()->create(['event_id' => $event->id, 'user_id' => $universityA->id]);
        EventParticipant::factory()->checkedIn()->create(['event_id' => $event->id, 'user_id' => $universityB->id]);
        EventParticipant::factory()->registered()->create(['event_id' => $event->id]);
        Task::factory()->done()->create(['event_id' => $event->id]);

        Sanctum::actingAs($owner, ['*']);

        $response = $this->getJson('/api/v1/events/'.$event->id.'/analytics?range=7')
            ->assertStatus(200)
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.range', 7);

        $trend = $response->json('data.trend');
        $this->assertCount(7, $trend['labels']);
        $this->assertCount(7, $trend['registered']);

        $institutions = array_column($response->json('data.participants.institutions'), 'label');
        $this->assertContains('Univ A', $institutions);
        $this->assertContains('Univ B', $institutions);

        $response
            ->assertJsonPath('data.participants.total', 3)
            ->assertJsonPath('data.funnel.checked_in', 2)
            ->assertJsonPath('data.tasks.total', 1);
        $this->assertCount(4, $response->json('data.participants.breakdown'));
        $this->assertCount(3, $response->json('data.tasks.by_status'));
    }

    public function test_analytics_endpoint_falls_back_to_default_range_on_invalid_value(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);

        $this->getJson('/api/v1/events/'.$event->id.'/analytics?range=15')
            ->assertStatus(200)
            ->assertJsonPath('data.range', 30);
    }

    public function test_participant_cannot_access_event_analytics(): void
    {
        $participant = $this->userWithRole('participant');
        $event = Event::factory()->published()->create();

        Sanctum::actingAs($participant, ['*']);

        $this->getJson('/api/v1/events/'.$event->id.'/analytics')->assertStatus(403);

        EventParticipant::factory()->registered()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $this->getJson('/api/v1/events/'.$event->id.'/analytics')->assertStatus(403);
    }

    public function test_endpoints_require_authentication(): void
    {
        $event = Event::factory()->published()->create();

        $this->getJson('/api/v1/dashboard/overview')->assertStatus(401);
        $this->getJson('/api/v1/events/'.$event->id.'/analytics')->assertStatus(401);
    }

    public function test_dashboard_is_empty_safe_without_data(): void
    {
        $owner = $this->userWithRole('event_owner');
        $event = Event::factory()->published()->create([
            'created_by' => $owner->id,
            'capacity' => 100,
            'starts_at' => now()->addWeeks(2),
        ]);

        Sanctum::actingAs($owner, ['*']);

        $trend = $this->getJson('/api/v1/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.kpi.activity.delta', null)
            ->assertJsonPath('data.kpi.task_completion.delta', null)
            ->assertJsonPath('data.kpi.overdue.value', 0)
            ->assertJsonPath('data.kpi.capacity.value', 0)
            ->assertJsonPath('data.kpi.attendance.applicable', false)
            ->assertJsonPath('data.attendance.rate', 0)
            ->assertJsonPath('data.funnel.registered', 0)
            ->json('data.health');

        $this->assertGreaterThanOrEqual(0, $trend['score']);
        $this->assertLessThanOrEqual(100, $trend['score']);
        $this->assertCount(4, $trend['factors']);

        $trendData = $this->getJson('/api/v1/dashboard/overview')->json('data.trend');
        $this->assertCount(30, $trendData['registered']);
        $this->assertSame(array_fill(0, 30, 0), $trendData['registered']);
    }

    private function createInstitutionalUser(string $institution): \App\Models\User
    {
        return $this->userWithRole('participant', ['institution' => $institution]);
    }
}
