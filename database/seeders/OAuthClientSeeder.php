<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Password Grant Client
        $passwordClient = Client::create([
            'name' => 'Astudio Password Grant Client',
            'secret' => 'VGQc4OKRDh0IvRyXV5p6h3cF8cZxUVHhvMBZf5q4',
            'provider' => 'users',
            'redirect' => 'http://localhost',
            'personal_access_client' => false,
            'password_client' => true,
            'revoked' => false,
        ]);

        // Create Personal Access Client
        $personalAccessClient = Client::create([
            'name' => 'Astudio Personal Access Client',
            'secret' => 'W22i8XlxOAGVzyKt1s4PJCVyxyjEQiyOfvzRhQVe',
            'provider' => null,
            'redirect' => 'http://localhost',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
        ]);

        // Add record to oauth_personal_access_clients table
        DB::table('oauth_personal_access_clients')->insert([
            'client_id' => $personalAccessClient->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
