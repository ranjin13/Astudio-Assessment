<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Timesheet>
 */
class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_name' => fake()->sentence(3),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'hours' => fake()->randomFloat(2, 0.5, 8),
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
        ];
    }
}
