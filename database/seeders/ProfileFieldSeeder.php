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
                'name' => 'Phone Number',
                'short_name' => 'phone_number',
                'input_type' => 'text',
                'required' => true,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Your contact phone number for important notifications',
                'options' => null,
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
                'name' => 'Preferred Learning Times',
                'short_name' => 'preferred_times',
                'input_type' => 'select_multiple',
                'required' => false,
                'is_active' => true,
                'sort_order' => 4,
                'description' => 'When do you prefer to study?',
                'options' => ['Early Morning (6-9 AM)', 'Morning (9-12 PM)', 'Afternoon (12-5 PM)', 'Evening (5-8 PM)', 'Night (8-11 PM)', 'Late Night (11 PM+)'],
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
        ];

        foreach ($fields as $field) {
            ProfileField::updateOrCreate(
                ['short_name' => $field['short_name']],
                $field
            );
        }
    }
}
