<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

class AttributeControllerTest extends TestCase
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

    #[Test]
    public function it_can_list_attributes()
    {
        // Create some test attributes
        Attribute::create([
            'name' => 'Test Text Attribute',
            'type' => 'text'
        ]);

        Attribute::create([
            'name' => 'Test Select Attribute',
            'type' => 'select',
            'options' => ["option 1","option 2"]
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/attributes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    #[Test]
    public function it_can_create_text_attribute()
    {
        $data = [
            'name' => 'Description',
            'type' => 'text'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/attributes', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => 'Description',
                'type' => 'text'
            ]);
    }

    #[Test] 
    public function it_can_create_select_attribute_with_options()
    {
        // Use a unique name with a timestamp to avoid conflicts
        $uniqueName = 'Priority_' . time();
        
        $data = [
            'name' => $uniqueName,
            'type' => 'select',
            'options' => ['High','Medium','Low']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/attributes', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'options',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => $uniqueName,
                'type' => 'select',
                'options' => ['High', 'Medium', 'Low']
            ]);
    }

    #[Test]
    public function it_validates_required_options_for_select_type()
    {
        $data = [
            'name' => 'Invalid Select',
            'type' => 'select'
            // Missing options
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/attributes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['options']);
    }

    #[Test]
    public function it_can_show_attribute()
    {
        $attribute = Attribute::create([
            'name' => 'Test Attribute',
            'type' => 'text'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson("/api/attributes/{$attribute->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'id' => $attribute->id,
                'name' => 'Test Attribute',
                'type' => 'text'
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_attribute()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/attributes/99999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Attribute with ID 99999 not found',
                'error_code' => 'ATTRIBUTE_NOT_FOUND'
            ]);
    }

    #[Test]
    public function it_can_update_attribute()
    {
        $attribute = Attribute::create([
            'name' => 'Old Name',
            'type' => 'text'
        ]);

        $data = [
            'name' => 'New Name',
            'type' => 'select',
            'options' => ['Option 1', 'Option 2']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->putJson("/api/attributes/{$attribute->id}", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'options',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => 'New Name',
                'type' => 'select',
                'options' => ['Option 1', 'Option 2']
            ]);
    }

    #[Test]
    public function it_can_delete_attribute()
    {
        $attribute = Attribute::create([
            'name' => 'To Be Deleted',
            'type' => 'text'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->deleteJson("/api/attributes/{$attribute->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('attributes', [
            'id' => $attribute->id
        ]);
    }

    #[Test]
    public function it_validates_unique_attribute_names()
    {
        // Create an initial attribute
        Attribute::create([
            'name' => 'Existing Name',
            'type' => 'text'
        ]);

        // Try to create another attribute with the same name
        $data = [
            'name' => 'Existing Name',
            'type' => 'text'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/attributes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_attribute_type()
    {
        $data = [
            'name' => 'Invalid Type',
            'type' => 'invalid_type'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/attributes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function it_can_update_attribute_without_changing_type()
    {
        $attribute = Attribute::create([
            'name' => 'Original Name',
            'type' => 'text'
        ]);

        $data = [
            'name' => 'Updated Name'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->putJson("/api/attributes/{$attribute->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'type' => 'text'
            ]);
    }

    #[Test]
    public function it_can_update_select_attribute_options()
    {
        $attribute = Attribute::create([
            'name' => 'Select Attribute',
            'type' => 'select',
            'options' => ['Option A', 'Option B']
        ]);

        $data = [
            'options' => ['Option X', 'Option Y', 'Option Z']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->putJson("/api/attributes/{$attribute->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Select Attribute',
                'type' => 'select',
                'options' => ['Option X', 'Option Y', 'Option Z']
            ]);
    }

    #[Test]
    public function it_returns_404_when_deleting_non_existent_attribute()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->deleteJson('/api/attributes/99999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Attribute with ID 99999 not found',
                'error_code' => 'ATTRIBUTE_NOT_FOUND'
            ]);
    }

    #[Test]
    public function it_can_filter_attributes_by_type()
    {
        // Create attributes of different types
        Attribute::create([
            'name' => 'Text Attribute',
            'type' => 'text'
        ]);

        Attribute::create([
            'name' => 'Number Attribute',
            'type' => 'number'
        ]);

        Attribute::create([
            'name' => 'Select Attribute',
            'type' => 'select',
            'options' => ['Option 1', 'Option 2']
        ]);

        // Filter by text type
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/attributes?filters[type]=text');

        $response->assertStatus(200);
        $responseData = $response->json();
        
        // Check that all returned attributes are of type 'text'
        foreach ($responseData['data'] as $attribute) {
            $this->assertEquals('text', $attribute['type']);
        }
        
        // Ensure we have at least one result
        $this->assertGreaterThan(0, count($responseData['data']));
    }
} 