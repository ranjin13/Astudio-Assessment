<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Models\Timesheet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 5 users if they don't exist
        $users = User::factory(5)->create();

        // Project statuses
        $statuses = ['active', 'on-hold', 'completed'];

        // For each user, create 3-4 projects
        foreach ($users as $user) {
            // Random number of projects (3-4) for this user
            $numProjects = rand(3, 4);
            
            // Ensure at least one project of each status
            $projectStatuses = array_merge(
                $statuses, // This ensures one of each status
                array_fill(0, $numProjects - count($statuses), fake()->randomElement($statuses)) // Fill remaining with random statuses
            );
            shuffle($projectStatuses); // Randomize the order
            
            // Create projects for this user
            for ($i = 0; $i < $numProjects; $i++) {
                $project = Project::factory()
                    ->withDates()
                    ->create([
                        'status' => $projectStatuses[$i]
                    ]);

                // Assign only the creating user to this project
                $project->users()->attach($user->id);

                // Create 1-3 timesheet entries for this project-user combination
                $numTimesheets = rand(1, 3);
                
                // Get the project's start and end dates
                $startDate = $project->attributeValues()
                    ->whereHas('attribute', fn($q) => $q->where('name', 'Start Date'))
                    ->first()?->value;
                
                $endDate = $project->attributeValues()
                    ->whereHas('attribute', fn($q) => $q->where('name', 'End Date'))
                    ->first()?->value;

                if ($startDate && $endDate) {
                    for ($j = 0; $j < $numTimesheets; $j++) {
                        Timesheet::create([
                            'user_id' => $user->id,
                            'project_id' => $project->id,
                            'date' => fake()->dateTimeBetween($startDate, $endDate)->format('Y-m-d'),
                            'hours' => fake()->randomFloat(2, 1, 8),
                            'task_name' => fake()->sentence(3),
                            'description' => fake()->paragraph(1)
                        ]);
                    }
                }
            }
        }
    }
}
