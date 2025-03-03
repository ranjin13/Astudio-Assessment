<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttributeValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = Attribute::all();
        $users = User::all();
        $projects = Project::all();

        // Create attribute values for users
        foreach ($users as $user) {
            foreach ($attributes as $attribute) {
                $value = $this->generateValueForAttribute($attribute);
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                    'value' => $value,
                ]);
            }
        }

        // Create attribute values for projects
        foreach ($projects as $project) {
            // Generate start date first
            $startDate = null;
            foreach ($attributes as $attribute) {
                $value = $this->generateValueForAttribute($attribute, $startDate);
                
                // Store start date to use for end date validation
                if ($attribute->name === 'Start Date') {
                    $startDate = $value;
                }
                
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'entity_type' => Project::class,
                    'entity_id' => $project->id,
                    'value' => $value,
                ]);
            }
        }
    }

    private function generateValueForAttribute(Attribute $attribute, ?string $startDate = null): string
    {
        return match ($attribute->type) {
            'text' => fake()->word(),
            'date' => $this->generateDate($attribute->name, $startDate),
            'number' => (string) fake()->numberBetween(1000, 100000),
            'select' => $this->getRandomOption($attribute->options),
            default => fake()->word(),
        };
    }

    private function generateDate(string $attributeName, ?string $startDate = null): string
    {
        if ($attributeName === 'End Date' && $startDate) {
            // Generate an end date that's between 1 month and 1 year after the start date
            $start = \Carbon\Carbon::parse($startDate);
            return fake()->dateTimeBetween($start->addMonth(), $start->copy()->addYear())->format('Y-m-d');
        }
        
        if ($attributeName === 'Start Date') {
            // Generate a start date between 6 months ago and 1 year in the future
            return fake()->dateTimeBetween('-6 months', '+1 year')->format('Y-m-d');
        }
        
        // For other date fields
        return fake()->date();
    }

    private function getRandomOption($options): string
    {
        if (!$options) {
            return 'default';
        }

        if (!is_array($options)) {
            $options = json_decode($options, true);
        }

        if (!is_array($options) || empty($options)) {
            return 'default';
        }

        return (string) fake()->randomElement($options);
    }
}
