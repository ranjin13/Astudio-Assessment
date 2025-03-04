<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Str;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private User $user;
    private string $token;
    private Attribute $textAttribute;
    private Attribute $selectAttribute;
    private Attribute $dateAttribute;

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

        // Generate a unique suffix for attribute names
        $uniqueSuffix = '_' . Str::random(10);

        // Create test attributes for use in projects
        $this->textAttribute = Attribute::create([
            'name' => 'Description' . $uniqueSuffix,
            'type' => 'text'
        ]);

        $this->selectAttribute = Attribute::create([
            'name' => 'Priority' . $uniqueSuffix,
            'type' => 'select',
            'options' => ['High', 'Medium', 'Low']
        ]);

        $this->dateAttribute = Attribute::create([
            'name' => 'Deadline' . $uniqueSuffix,
            'type' => 'date'
        ]);
    }

    #[Test]
    public function it_can_list_projects()
    {
        // Create some test projects
        $project1 = Project::create([
            'name' => 'Test Project 1',
            'status' => 'active'
        ]);
        $project1->users()->attach($this->user->id);

        $project2 = Project::create([
            'name' => 'Test Project 2',
            'status' => 'completed'
        ]);
        $project2->users()->attach($this->user->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'attributes',
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
    public function it_can_create_project()
    {
        $data = [
            'name' => 'New Project',
            'status' => 'active',
            'attributes' => [
                [
                    'attribute_id' => $this->textAttribute->id,
                    'value' => 'Project description text'
                ],
                [
                    'attribute_id' => $this->selectAttribute->id,
                    'value' => 'High'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/projects', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'status',
                'attributes',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => 'New Project',
                'status' => 'active'
            ]);

        // Verify that the project was created with the correct attributes
        $projectId = $response->json('id');
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'New Project',
            'status' => 'active'
        ]);

        // Verify that the user was automatically assigned to the project
        $this->assertDatabaseHas('project_user', [
            'project_id' => $projectId,
            'user_id' => $this->user->id
        ]);

        // Verify that the attribute values were created
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $this->textAttribute->id,
            'entity_type' => 'App\\Models\\Project',
            'entity_id' => $projectId,
            'value' => 'Project description text'
        ]);

        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $this->selectAttribute->id,
            'entity_type' => 'App\\Models\\Project',
            'entity_id' => $projectId,
            'value' => 'High'
        ]);
    }

    #[Test]
    public function it_validates_project_data()
    {
        $data = [
            // Missing required 'name' field
            'status' => 'invalid_status' // Invalid status
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/projects', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'status']);
    }

    #[Test]
    public function it_validates_attribute_values()
    {
        $data = [
            'name' => 'New Project',
            'status' => 'active',
            'attributes' => [
                [
                    'attribute_id' => $this->selectAttribute->id,
                    'value' => 'Invalid Option' // Not in the options array
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->postJson('/api/projects', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attributes.0.value']);
    }

    #[Test]
    public function it_can_show_project()
    {
        // Create a project with attributes
        $project = Project::create([
            'name' => 'Test Project',
            'status' => 'active'
        ]);
        $project->users()->attach($this->user->id);

        // Add attribute values
        $project->attributeValues()->create([
            'attribute_id' => $this->textAttribute->id,
            'value' => 'Project description'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'status',
                'attributes',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'id' => $project->id,
                'name' => 'Test Project',
                'status' => 'active'
            ]);

        // Check that the attributes are included in the response
        $attributes = $response->json('attributes');
        $this->assertNotEmpty($attributes);
        
        // Find the attribute with the matching name
        $attributeName = $this->textAttribute->name;
        $foundAttribute = null;
        foreach ($attributes as $attribute) {
            if (isset($attribute['name']) && $attribute['name'] === $attributeName) {
                $foundAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($foundAttribute, "Attribute with name '{$attributeName}' not found in response");
        $this->assertEquals('Project description', $foundAttribute['value']);
    }

    #[Test]
    public function it_returns_404_for_non_existent_project()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/projects/99999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Project with ID 99999 not found',
                'error_code' => 'PROJECT_NOT_FOUND'
            ]);
    }

    #[Test]
    public function it_can_update_project()
    {
        // Create a project with attributes
        $project = Project::create([
            'name' => 'Original Name',
            'status' => 'active'
        ]);
        $project->users()->attach($this->user->id);

        // Add attribute values
        $project->attributeValues()->create([
            'attribute_id' => $this->textAttribute->id,
            'value' => 'Original description'
        ]);

        $data = [
            'name' => 'Updated Name',
            'status' => 'completed',
            'attributes' => [
                [
                    'attribute_id' => $this->textAttribute->id,
                    'value' => 'Updated description'
                ],
                [
                    'attribute_id' => $this->selectAttribute->id,
                    'value' => 'Medium'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->putJson("/api/projects/{$project->id}", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'status',
                'attributes',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => 'Updated Name',
                'status' => 'completed'
            ]);

        // Verify that the project was updated
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Name',
            'status' => 'completed'
        ]);

        // Verify that the attribute values were updated
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $this->textAttribute->id,
            'entity_type' => 'App\\Models\\Project',
            'entity_id' => $project->id,
            'value' => 'Updated description'
        ]);

        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $this->selectAttribute->id,
            'entity_type' => 'App\\Models\\Project',
            'entity_id' => $project->id,
            'value' => 'Medium'
        ]);
    }

    #[Test]
    public function it_can_update_project_without_changing_attributes()
    {
        // Create a project with attributes
        $project = Project::create([
            'name' => 'Original Name',
            'status' => 'active'
        ]);
        $project->users()->attach($this->user->id);

        // Add attribute values
        $project->attributeValues()->create([
            'attribute_id' => $this->textAttribute->id,
            'value' => 'Original description'
        ]);

        $data = [
            'name' => 'Updated Name',
            'status' => 'completed'
            // No attributes field
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->putJson("/api/projects/{$project->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'status' => 'completed'
            ]);

        // Verify that the attribute values were not changed
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $this->textAttribute->id,
            'entity_type' => 'App\\Models\\Project',
            'entity_id' => $project->id,
            'value' => 'Original description'
        ]);
    }

    #[Test]
    public function it_can_delete_project()
    {
        $project = Project::create([
            'name' => 'To Be Deleted',
            'status' => 'active'
        ]);
        $project->users()->attach($this->user->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id
        ]);

        // Verify that the project_user relationship was deleted (cascade)
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $this->user->id
        ]);
    }

    #[Test]
    public function it_returns_404_when_deleting_non_existent_project()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->deleteJson('/api/projects/99999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Project with ID 99999 not found',
                'error_code' => 'PROJECT_NOT_FOUND'
            ]);
    }

    #[Test]
    public function it_can_filter_projects_by_status()
    {
        // Create projects with different statuses
        $activeProject = Project::create([
            'name' => 'Active Project',
            'status' => 'active'
        ]);
        $activeProject->users()->attach($this->user->id);

        $completedProject = Project::create([
            'name' => 'Completed Project',
            'status' => 'completed'
        ]);
        $completedProject->users()->attach($this->user->id);

        $onHoldProject = Project::create([
            'name' => 'On Hold Project',
            'status' => 'on-hold'
        ]);
        $onHoldProject->users()->attach($this->user->id);

        // Filter by active status
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/projects?filters[status]=active');

        $response->assertStatus(200);
        $responseData = $response->json();
        
        // Check that all returned projects have 'active' status
        foreach ($responseData['data'] as $project) {
            $this->assertEquals('active', $project['status']);
        }
        
        // Ensure we have at least one result
        $this->assertGreaterThan(0, count($responseData['data']));
    }

    #[Test]
    public function it_can_filter_projects_by_name()
    {
        // Create projects with different names
        $project1 = Project::create([
            'name' => 'Marketing Campaign',
            'status' => 'active'
        ]);
        $project1->users()->attach($this->user->id);

        $project2 = Project::create([
            'name' => 'Website Redesign',
            'status' => 'active'
        ]);
        $project2->users()->attach($this->user->id);

        // Filter by name containing "Marketing"
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/projects?filters[name]=Marketing');

        $response->assertStatus(200);
        $responseData = $response->json();
        
        // Check that all returned projects have "Marketing" in their name
        foreach ($responseData['data'] as $project) {
            $this->assertStringContainsString('Marketing', $project['name']);
        }
        
        // Ensure we have at least one result
        $this->assertGreaterThan(0, count($responseData['data']));
    }

    #[Test]
    public function it_can_filter_projects_by_attribute_value()
    {
        // Create projects with different attribute values
        $project1 = Project::create([
            'name' => 'Project with High Priority',
            'status' => 'active'
        ]);
        $project1->users()->attach($this->user->id);
        $project1->attributeValues()->create([
            'attribute_id' => $this->selectAttribute->id,
            'value' => 'High'
        ]);

        $project2 = Project::create([
            'name' => 'Project with Low Priority',
            'status' => 'active'
        ]);
        $project2->users()->attach($this->user->id);
        $project2->attributeValues()->create([
            'attribute_id' => $this->selectAttribute->id,
            'value' => 'Low'
        ]);

        // Filter by Priority attribute with value "High"
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->getJson('/api/projects?filters[' . $this->selectAttribute->name . ']=High');

        $response->assertStatus(200);
        $responseData = $response->json();
        
        // Ensure we have at least one result
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Check that the returned project has the correct name
        $this->assertEquals('Project with High Priority', $responseData['data'][0]['name']);
    }
} 