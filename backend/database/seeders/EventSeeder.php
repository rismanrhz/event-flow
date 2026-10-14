<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\ParticipantStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventParticipant;
use App\Models\Permission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@eventflow.test')->firstOrFail();
        $admin = User::where('email', 'admin@eventflow.test')->firstOrFail();

        $events = [
            [
                'title' => 'Tech Conference 2026',
                'slug' => 'tech-conference-2026',
                'description' => 'A full-day conference covering the latest in web, cloud, and AI technology.',
                'location' => 'Jakarta Convention Center',
                'starts_at' => now()->addMonths(2)->startOfDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addMonths(2)->startOfDay()->addHours(8)->format('Y-m-d H:i:s'),
                'capacity' => 500,
                'status' => EventStatus::Published,
                'created_by' => $owner->id,
            ],
            [
                'title' => 'Community Charity Run',
                'slug' => 'community-charity-run',
                'description' => '5K charity run to raise funds for local education programs.',
                'location' => 'Monas Park, Jakarta',
                'starts_at' => now()->subMonths(3)->startOfDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->subMonths(3)->startOfDay()->addHours(4)->format('Y-m-d H:i:s'),
                'capacity' => 1000,
                'status' => EventStatus::Completed,
                'created_by' => $owner->id,
            ],
            [
                'title' => 'Design Workshop',
                'slug' => 'design-workshop',
                'description' => 'Hands-on workshop on modern UI/UX design principles.',
                'location' => 'Coffeeworks Bandung',
                'starts_at' => now()->addWeeks(3)->startOfDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addWeeks(3)->startOfDay()->addHours(6)->format('Y-m-d H:i:s'),
                'capacity' => 60,
                'status' => EventStatus::Draft,
                'created_by' => $owner->id,
            ],
            [
                'title' => 'Annual Developer Summit',
                'slug' => 'annual-developer-summit',
                'description' => 'Annual summit with workshops, talks, and networking for developers.',
                'location' => 'Balai Sarbini, Jakarta',
                'starts_at' => now()->addMonths(4)->startOfDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addMonths(4)->startOfDay()->addHours(8)->format('Y-m-d H:i:s'),
                'capacity' => 800,
                'status' => EventStatus::Published,
                'created_by' => $admin->id,
            ],
        ];

        foreach ($events as $event) {
            Event::query()->updateOrCreate(
                ['slug' => $event['slug']],
                $event
            );
        }

        $this->seedCommitteeAndTasks($owner);
        $this->seedParticipants();
        $this->seedHistoricalParticipants();
        $this->seedAdditionalTasks();
        $this->seedActivityLogs();
    }

    private function seedCommitteeAndTasks(User $owner): void
    {
        $tech = Event::where('slug', 'tech-conference-2026')->firstOrFail();
        $budi = User::where('email', 'committee@eventflow.test')->firstOrFail();

        $member = EventMember::query()->firstOrCreate(
            ['event_id' => $tech->id, 'user_id' => $budi->id],
            ['role_title' => 'Secretary']
        );

        $permissionIds = Permission::query()
            ->whereIn('key', ['manage_tasks', 'manage_schedule'])
            ->pluck('id');

        $member->permissions()->syncWithoutDetaching($permissionIds);

        $tasks = [
            [
                'title' => 'Book conference venue',
                'description' => 'Confirm the main hall and breakout rooms with JCC.',
                'status' => TaskStatus::Done,
                'priority' => TaskPriority::High,
                'due_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Design event branding',
                'description' => 'Create the logo, banners, and lanyard artwork.',
                'status' => TaskStatus::InProgress,
                'priority' => TaskPriority::Medium,
                'due_at' => now()->addWeeks(4)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Send invitations to speakers',
                'description' => 'Email confirmed speakers with the schedule and logistics.',
                'status' => TaskStatus::Todo,
                'priority' => TaskPriority::Low,
                'due_at' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            ],
        ];

        foreach ($tasks as $task) {
            Task::query()->firstOrCreate(
                ['event_id' => $tech->id, 'title' => $task['title']],
                array_merge($task, ['assignee_id' => $budi->id, 'created_by' => $owner->id])
            );
        }
    }

    private function seedParticipants(): void
    {
        $siti = User::where('email', 'participant@eventflow.test')->firstOrFail();

        $tech = Event::where('slug', 'tech-conference-2026')->firstOrFail();
        EventParticipant::query()->firstOrCreate(
            ['event_id' => $tech->id, 'user_id' => $siti->id],
            ['registration_code' => Str::upper(Str::random(10)), 'status' => ParticipantStatus::Registered]
        );

        $charity = Event::where('slug', 'community-charity-run')->firstOrFail();
        EventParticipant::query()->firstOrCreate(
            ['event_id' => $charity->id, 'user_id' => $siti->id],
            [
                'registration_code' => Str::upper(Str::random(10)),
                'status' => ParticipantStatus::CheckedIn,
                'checked_in_at' => now()->subMonths(3)->startOfDay()->addHours(7),
            ]
        );
    }

    /**
     * Synthetic audiences across 60–90 days so the analytics views have history to
     * visualize. Deterministic emails keep the seeder idempotent across re-runs.
     */
    private function seedHistoricalParticipants(): void
    {
        $tech = Event::where('slug', 'tech-conference-2026')->firstOrFail();
        $charity = Event::where('slug', 'community-charity-run')->firstOrFail();
        $summit = Event::where('slug', 'annual-developer-summit')->firstOrFail();

        $registrations = [
            $tech->id => [
                ['count' => 6, 'days_ago' => 55, 'status' => ParticipantStatus::Registered],
                ['count' => 9, 'days_ago' => 42, 'status' => ParticipantStatus::Registered],
                ['count' => 8, 'days_ago' => 22, 'status' => ParticipantStatus::Registered],
                ['count' => 6, 'days_ago' => 12, 'status' => ParticipantStatus::Registered],
                ['count' => 7, 'days_ago' => 4, 'status' => ParticipantStatus::Registered],
                ['count' => 3, 'days_ago' => 1, 'status' => ParticipantStatus::Waitlisted],
                ['count' => 2, 'days_ago' => 30, 'status' => ParticipantStatus::Cancelled],
            ],
            $summit->id => [
                ['count' => 8, 'days_ago' => 35, 'status' => ParticipantStatus::Registered],
                ['count' => 5, 'days_ago' => 18, 'status' => ParticipantStatus::Registered],
                ['count' => 2, 'days_ago' => 6, 'status' => ParticipantStatus::Waitlisted],
            ],
            $charity->id => [
                ['count' => 9, 'days_ago' => 80, 'status' => ParticipantStatus::CheckedIn],
                ['count' => 6, 'days_ago' => 78, 'status' => ParticipantStatus::CheckedIn],
                ['count' => 4, 'days_ago' => 90, 'status' => ParticipantStatus::Registered],
                ['count' => 1, 'days_ago' => 85, 'status' => ParticipantStatus::Cancelled],
            ],
        ];

        $institutions = ['Universitas Indonesia', 'Institut Teknologi Bandung', 'Bina Nusantara University', 'Gadjah Mada University', 'Gojek Indonesia', 'Bank Mandiri', 'Telkom Indonesia', null, null, null];

        $index = 1;
        foreach ($registrations as $eventId => $cohorts) {
            foreach ($cohorts as $cohort) {
                for ($i = 0; $i < $cohort['count']; $i++) {
                    $user = $this->demoUser($index, $institutions[($index + $i) % count($institutions)]);
                    $index++;

                    EventParticipant::query()->firstOrCreate(
                        ['event_id' => $eventId, 'user_id' => $user->id],
                        $this->participantPayload($cohort['status'], $cohort['days_ago'], $eventId === $charity->id)
                    );
                }
            }
        }
    }

    private function demoUser(int $index, ?string $institution): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'demo'.$index.'@eventflow.test'],
            [
                'name' => 'Demo Participant '.$index,
                'password' => 'demo-password',
                'is_active' => true,
                'institution' => $institution,
            ]
        );
    }

    private function participantPayload(ParticipantStatus $status, int $daysAgo, bool $onEventDay): array
    {
        $createdAt = now()->subDays($daysAgo);

        if ($status === ParticipantStatus::CheckedIn) {
            return [
                'registration_code' => Str::upper(Str::random(10)),
                'status' => ParticipantStatus::CheckedIn,
                'checked_in_at' => $onEventDay
                    ? $createdAt->startOfDay()->addHours(rand(6, 8))->addMinutes(rand(0, 55))
                    : $createdAt->addHours(rand(1, 5)),
                'created_at' => $createdAt->subDay(),
            ];
        }

        if ($status === ParticipantStatus::Cancelled) {
            return [
                'registration_code' => Str::upper(Str::random(10)),
                'status' => ParticipantStatus::Cancelled,
                'cancelled_at' => $createdAt->addDays(rand(1, 5)),
                'cancel_reason' => 'Personal schedule conflict',
                'created_at' => $createdAt,
            ];
        }

        return [
            'registration_code' => Str::upper(Str::random(10)),
            'status' => $status,
            'created_at' => $createdAt,
        ];
    }

    private function seedAdditionalTasks(): void
    {
        $tech = Event::where('slug', 'tech-conference-2026')->firstOrFail();
        $owner = User::where('email', 'owner@eventflow.test')->firstOrFail();
        $budi = User::where('email', 'committee@eventflow.test')->firstOrFail();

        $tasks = [
            ['title' => 'Confirm speaker travel bookings', 'status' => TaskStatus::Done, 'priority' => TaskPriority::High, 'due_at' => now()->subWeeks(2)],
            ['title' => 'Publish agenda on website', 'status' => TaskStatus::Done, 'priority' => TaskPriority::Medium, 'due_at' => now()->subWeeks(1)],
            ['title' => 'Print attendee badges', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'due_at' => now()->subDays(2)],
            ['title' => 'Coordinate F&B with caterer', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::Medium, 'due_at' => now()->addWeeks(1)],
            ['title' => 'Send reminder emails to attendees', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::Low, 'due_at' => now()->addWeeks(3)],
        ];

        foreach ($tasks as $task) {
            $persisted = Task::query()->firstOrCreate(
                ['event_id' => $tech->id, 'title' => $task['title']],
                array_merge($task, [
                    'due_at' => $task['due_at']->format('Y-m-d H:i:s'),
                    'assignee_id' => $budi->id,
                    'created_by' => $owner->id,
                ])
            );

            if ($task['status'] === TaskStatus::Done) {
                $persisted->update([
                    'status' => TaskStatus::Done,
                    'updated_at' => $task['due_at']->addHours(2)->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function seedActivityLogs(): void
    {
        $tech = Event::where('slug', 'tech-conference-2026')->firstOrFail();
        $owner = User::where('email', 'owner@eventflow.test')->firstOrFail();
        $budi = User::where('email', 'committee@eventflow.test')->firstOrFail();
        $demo = User::where('email', 'demo1@eventflow.test')->first();

        $logs = [
            ['user_id' => $budi->id, 'action' => 'task.updated', 'module' => 'task', 'description' => 'Moved "Print attendee badges" to in progress.', 'created_at' => now()->subHours(3)],
            ['user_id' => $demo?->id, 'action' => 'event.registration', 'module' => 'registration', 'description' => 'A new attendee registered for the event.', 'created_at' => now()->subDay()],
            ['user_id' => $owner->id, 'action' => 'event.published', 'module' => 'event', 'description' => 'Event details and agenda updated.', 'created_at' => now()->subDays(2)],
            ['user_id' => $budi->id, 'action' => 'task.done', 'module' => 'task', 'description' => 'Completed "Publish agenda on website".', 'created_at' => now()->subDays(6)],
            ['user_id' => $owner->id, 'action' => 'event.updated', 'module' => 'event', 'description' => 'Venue capacity adjusted to 500.', 'created_at' => now()->subDays(9)],
            ['user_id' => $demo?->id, 'action' => 'event.registration', 'module' => 'registration', 'description' => 'A new attendee registered for the event.', 'created_at' => now()->subDays(4)],
        ];

        foreach ($logs as $log) {
            if ($log['user_id'] === null) {
                continue;
            }

            ActivityLog::query()->firstOrCreate(
                ['event_id' => $tech->id, 'action' => $log['action'], 'created_at' => $log['created_at']->format('Y-m-d H:i:s')],
                $log + ['event_id' => $tech->id, 'created_at' => $log['created_at']->format('Y-m-d H:i:s')]
            );
        }
    }
}
