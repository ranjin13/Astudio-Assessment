<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'Department',
                'type' => 'select',
                'options' => ['IT', 'HR', 'Finance', 'Marketing'],
            ],
            [
                'name' => 'Start Date',
                'type' => 'date',
                'options' => null,
            ],
            [
                'name' => 'End Date',
                'type' => 'date',
                'options' => null,
            ],
            [
                'name' => 'Employee ID',
                'type' => 'text',
                'options' => null,
            ],
            [
                'name' => 'Budget',
                'type' => 'number',
                'options' => null,
            ],
            [
                'name' => 'Priority',
                'type' => 'select',
                'options' => ['Low', 'Medium', 'High'],
            ],
        ];

        foreach ($attributes as $attribute) {
            Attribute::create($attribute);
        }
    }
}
