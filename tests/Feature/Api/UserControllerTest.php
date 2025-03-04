<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\Hash;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a personal access client for testing if it doesn't exist
        if (!Client::where('personal_access_client', true)->exists()) {
            $client = Client::create([
                'name' => 'Test Personal Access Client',
                'secret' => 'secret',
                'provider' => 'users',
                'redirect' => 'http://localhost',
                'personal_access_client' => true,
                'password_client' => false,
                'revoked' => false,
            ]);
            
            // Create the oauth_personal_access_clients record
            $client->personalAccessClient()->create([
                'client_id' => $client->id,
            ]);
        }

        // Create a user and generate a token for authentication
        $this->user = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('Password123!')
        ]);

        $this->token = $this->user->createToken('Test Token')->accessToken;
    }

    public function test_can_list_users()
    {
        // Create additional users
        User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('Password123!')
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_user()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'created_at',
                'updated_at'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name']
        ]);
    }

    public function test_cannot_create_user_with_existing_email()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->user->email, // Using existing email
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_show_user()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/' . $this->user->id);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email
            ]);
    }

    public function test_cannot_show_nonexistent_user()
    {
        $nonExistentId = User::max('id') + 1;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/users/' . $nonExistentId);

        $response->assertStatus(404);
    }

    public function test_can_update_user()
    {
        $updateData = [
            'first_name' => 'Updated First Name',
            'last_name' => 'Updated Last Name',
            'email' => $this->faker->unique()->safeEmail
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/' . $this->user->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->user->id,
                'first_name' => $updateData['first_name'],
                'last_name' => $updateData['last_name'],
                'email' => $updateData['email']
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => $updateData['first_name'],
            'last_name' => $updateData['last_name'],
            'email' => $updateData['email']
        ]);
    }

    public function test_can_update_user_password()
    {
        $newPassword = 'NewPassword123!';
        $updateData = [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'password' => $newPassword
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/' . $this->user->id, $updateData);

        $response->assertStatus(200);

        // Verify the password was updated by attempting to login
        $loginResponse = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $newPassword
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'access_token',
                'token_type'
            ]);
    }

    public function test_cannot_update_nonexistent_user()
    {
        $nonExistentId = User::max('id') + 1;
        $updateData = [
            'first_name' => 'Updated First Name',
            'last_name' => 'Updated Last Name'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/' . $nonExistentId, $updateData);

        $response->assertStatus(404);
    }

    public function test_can_delete_user()
    {
        // Create a user to delete
        $userToDelete = User::create([
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('Password123!')
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/users/' . $userToDelete->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_cannot_delete_nonexistent_user()
    {
        $nonExistentId = User::max('id') + 1;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/users/' . $nonExistentId);

        $response->assertStatus(404);
    }

    public function test_unauthorized_access_is_rejected()
    {
        // Test without authentication token
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);

        $response = $this->getJson('/api/users/' . $this->user->id);
        $response->assertStatus(401);

        $response = $this->postJson('/api/users', [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ]);
        $response->assertStatus(401);

        $response = $this->putJson('/api/users/' . $this->user->id, [
            'first_name' => 'Updated Name'
        ]);
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/users/' . $this->user->id);
        $response->assertStatus(401);
    }

    public function test_validation_rules_for_create()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/users', [
            'first_name' => '',
            'last_name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email',
                'password'
            ]);
    }

    public function test_validation_rules_for_update()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/users/' . $this->user->id, [
            'first_name' => '',
            'last_name' => '',
            'email' => 'not-an-email'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email'
            ]);
    }
} 