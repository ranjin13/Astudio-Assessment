<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Database\Seeder;

class TimesheetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        foreach ($users as $user) {
            // Get projects assigned to the user
            $projects = $user->projects;
            
            // Create 3-5 timesheet entries per project for each user
            foreach ($projects as $project) {
                Timesheet::factory(rand(3, 5))->create([
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                ]);
            }
        }
    }
}
