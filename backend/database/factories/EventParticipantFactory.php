<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'registration_code' => Str::upper(Str::random(10)),
            'status' => ParticipantStatus::Registered,
            'created_by' => null,
        ];
    }

    public function registered(): static
    {
        return $this->state(['status' => ParticipantStatus::Registered]);
    }

    public function checkedIn(): static
    {
        return $this->state([
            'status' => ParticipantStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function waitlisted(): static
    {
        return $this->state(['status' => ParticipantStatus::Waitlisted]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => ParticipantStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
