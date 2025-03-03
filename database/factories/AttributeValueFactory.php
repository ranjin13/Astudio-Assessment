<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attribute = Attribute::factory()->create();
        $entityTypes = [
            User::class => User::factory(),
            Project::class => Project::factory(),
        ];
        $entityType = fake()->randomElement(array_keys($entityTypes));
        $entity = $entityTypes[$entityType];

        $value = match ($attribute->type) {
            'text' => fake()->sentence(),
            'date' => fake()->date(),
            'number' => (string) fake()->numberBetween(1, 100),
            'select' => json_decode($attribute->options)[0] ?? 'option1',
            default => fake()->word(),
        };

        return [
            'attribute_id' => $attribute->id,
            'entity_type' => $entityType,
            'entity_id' => $entity,
            'value' => $value,
        ];
    }
}
