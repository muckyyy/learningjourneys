<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use Illuminate\Database\Seeder;

class ProfileFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fields = [
             [
                'name' => 'Gender',
                'short_name' => 'gender',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Your current gender identity',
                'options' => ['Male', 'Female', 'Prefer not to say'],
            ],
            
            [
                'name' => 'Department',
                'short_name' => 'department',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Your department or field of study',
                'options' => ['Computer Science', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Engineering', 'Business', 'Other'],
            ],
            [
                'name' => 'Learning Goals',
                'short_name' => 'learning_goals',
                'input_type' => 'textarea',
                'required' => false,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'What do you hope to achieve with this learning platform?',
                'options' => null,
            ],
            [
                'name' => 'Experience Level',
                'short_name' => 'experience_level',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 5,
                'description' => 'Your current level of experience in the subject matter',
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            [
                'name' => 'Year of birth',
                'short_name' => 'year_of_birth',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 6,
                'description' => 'Your year of birth',
                'options' => range(date('Y'), 1900),
            ]
        ];

        foreach ($fields as $field) {
            ProfileField::updateOrCreate(
                ['short_name' => $field['short_name']],
                $field
            );
        }
    }
}
