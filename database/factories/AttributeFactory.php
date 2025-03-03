<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['text', 'date', 'number', 'select']);
        $options = $type === 'select' ? ['option1', 'option2', 'option3'] : null;

        return [
            'name' => fake()->unique()->word(),
            'type' => $type,
            'options' => $options ? json_encode($options) : null,
        ];
    }
}
