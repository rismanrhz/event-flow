<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'location' => fake()->city().', '.fake()->country(),
            'starts_at' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'ends_at' => fake()->dateTimeBetween('+3 months', '+4 months'),
            'capacity' => fake()->optional()->numberBetween(50, 1000),
            'status' => EventStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Completed,
            'starts_at' => fake()->dateTimeBetween('-4 months', '-2 months'),
            'ends_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Cancelled,
        ]);
    }
}
