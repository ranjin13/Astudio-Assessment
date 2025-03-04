<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

class TimesheetControllerTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected $user;
    protected $token;
    protected $project;
    protected $timesheet;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create a test project and associate it with the user
        $this->project = Project::create([
            'name' => 'Test Project',
            'status' => 'active'
        ]);
        $this->project->users()->attach($this->user->id);

        // Create a test timesheet
        $this->timesheet = Timesheet::create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => now()->format('Y-m-d'),
            'hours' => 8.5,
            'task_name' => 'Test Task'
        ]);

        // Create access token
        $this->token = $this->user->createToken('Test Token')->accessToken;
    }

    #[Test]
    public function it_can_list_timesheets()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/timesheets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'user_id',
                        'date',
                        'hours',
                        'task_name',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);
    }

    #[Test]
    public function it_can_create_timesheet()
    {
        $data = [
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => now()->format('Y-m-d'),
            'hours' => 7.5,
            'task_name' => 'New Task'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/timesheets', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'project_id',
                'user_id',
                'date',
                'hours',
                'task_name',
                'created_at',
                'updated_at'
            ]);

        $this->assertDatabaseHas('timesheets', [
            'project_id' => $data['project_id'],
            'user_id' => $data['user_id'],
            'date' => $data['date'],
            'hours' => $data['hours'],
            'task_name' => $data['task_name']
        ]);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/timesheets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'date', 'hours', 'task_name']);
    }

    #[Test]
    public function it_validates_hours_range()
    {
        $data = [
            'project_id' => $this->project->id,
            'date' => now()->format('Y-m-d'),
            'hours' => 25, // Invalid hours
            'task_name' => 'Test Task'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/timesheets', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hours']);
    }

    #[Test]
    public function it_can_show_timesheet()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/timesheets/{$this->timesheet->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->timesheet->id,
                'project_id' => $this->timesheet->project_id,
                'user_id' => $this->timesheet->user_id,
                'date' => $this->timesheet->date->format('Y-m-d\TH:i:s.u\Z'),
                'hours' => $this->timesheet->hours,
                'task_name' => $this->timesheet->task_name
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_timesheet()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/timesheets/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Timesheet with ID 99999 not found',
                'error_code' => 'TIMESHEET_NOT_FOUND'
            ]);
    }

    #[Test]
    public function it_can_update_timesheet()
    {
        $data = [
            'hours' => 9.0,
            'task_name' => 'Updated Task'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->put("/api/timesheets/{$this->timesheet->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->timesheet->id,
                'hours' => $data['hours'],
                'task_name' => $data['task_name']
            ]);

        $this->assertDatabaseHas('timesheets', [
            'id' => $this->timesheet->id,
            'hours' => $data['hours'],
            'task_name' => $data['task_name']
        ]);
    }

    #[Test]
    public function it_can_delete_timesheet()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->delete("/api/timesheets/{$this->timesheet->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('timesheets', [
            'id' => $this->timesheet->id
        ]);
    }

    #[Test]
    public function it_can_filter_timesheets_by_date()
    {
        $date = now()->format('Y-m-d');
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/timesheets?filters[date]=$date");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'date'
                    ]
                ]
            ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'date' => $this->timesheet->date->format('Y-m-d\TH:i:s.u\Z')
                ]
            ]
        ]);
    }

    #[Test]
    public function it_can_filter_timesheets_by_hours()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/timesheets?filters[hours]=8.5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'hours'
                    ]
                ]
            ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'hours' => 8.5
                ]
            ]
        ]);
    }

    #[Test]
    public function it_can_filter_timesheets_by_task_name()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/timesheets?filters[task_name]=Test Task');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_name'
                    ]
                ]
            ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'task_name' => 'Test Task'
                ]
            ]
        ]);
    }

    #[Test]
    public function it_cannot_access_other_users_timesheets()
    {
        // Create another user and their timesheet
        $otherUser = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        $otherProject = Project::create([
            'name' => 'Other Project',
            'status' => 'active'
        ]);
        $otherProject->users()->attach($otherUser->id);

        $otherTimesheet = Timesheet::create([
            'user_id' => $otherUser->id,
            'project_id' => $otherProject->id,
            'date' => now()->format('Y-m-d'),
            'hours' => 8.0,
            'task_name' => 'Other Task'
        ]);

        // Try to access the other user's timesheet
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/timesheets/{$otherTimesheet->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Access denied. You can only view your own timesheets.',
                'error_code' => 'TIMESHEET_ACCESS_DENIED'
            ]);
    }
} 