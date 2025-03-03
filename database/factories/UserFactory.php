<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Str::lower($firstName . '.' . $lastName . '@' . fake()->domainName()),
            'password' => Hash::make('password'), // Default password for all test users
        ];
    }
}
