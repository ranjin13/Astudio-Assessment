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
            foreach ($attributes as $attribute) {
                $value = $this->generateValueForAttribute($attribute);
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'entity_type' => Project::class,
                    'entity_id' => $project->id,
                    'value' => $value,
                ]);
            }
        }
    }

    private function generateValueForAttribute(Attribute $attribute): string
    {
        return match ($attribute->type) {
            'text' => fake()->word(),
            'date' => fake()->date(),
            'number' => (string) fake()->numberBetween(1000, 100000),
            'select' => $this->getRandomOption($attribute->options),
            default => fake()->word(),
        };
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
