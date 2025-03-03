<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' ' . fake()->word() . ' Project',
            'status' => fake()->randomElement(['active', 'completed', 'on-hold']),
        ];
    }

    /**
     * Configure the model factory to add date attributes.
     *
     * @return $this
     */
    public function withDates()
    {
        return $this->afterCreating(function (Project $project) {
            // Get the Start Date and End Date attributes
            $startDateAttr = Attribute::where('name', 'Start Date')->first();
            $endDateAttr = Attribute::where('name', 'End Date')->first();

            if (!$startDateAttr || !$endDateAttr) {
                return;
            }

            // Generate a random start date between 6 months ago and now
            $startDate = fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d');
            
            // Generate an end date that's between 1 month and 1 year after the start date
            $endDate = fake()->dateTimeBetween(
                Carbon::parse($startDate)->addMonth(),
                Carbon::parse($startDate)->addYear()
            )->format('Y-m-d');

            // Create the attribute values
            AttributeValue::create([
                'attribute_id' => $startDateAttr->id,
                'entity_type' => Project::class,
                'entity_id' => $project->id,
                'value' => $startDate,
            ]);

            AttributeValue::create([
                'attribute_id' => $endDateAttr->id,
                'entity_type' => Project::class,
                'entity_id' => $project->id,
                'value' => $endDate,
            ]);
        });
    }
}
