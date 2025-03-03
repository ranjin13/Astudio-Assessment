<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        // Create 3 projects
        Project::factory(3)->create()->each(function ($project) use ($users) {
            // Assign 2-3 random users to each project
            $project->users()->attach(
                $users->random(rand(2, 3))->pluck('id')->toArray()
            );
        });
    }
}
