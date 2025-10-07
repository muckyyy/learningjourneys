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
                'name' => 'Year of birth',
                'short_name' => 'year_of_birth',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 6,
                'description' => 'Your year of birth',
                'options' => range(date('Y'), 1900),
            ],
            
            [
                'name' => 'Country',
                'short_name' => 'country',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'The country you are currently living in',
                'options' => ['United States', 'Canada', 'United Kingdom', 'Australia', 'Switzerland', 'Other'],
            ],
            [
                'name' => 'City',
                'short_name' => 'city',
                'input_type' => 'text',
                'required' => true,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'The city you are currently living in',
            ],
            [
                'name' => 'Life stage',
                'short_name' => 'life_stage',
                'input_type' => 'select',
                'required' => true,
                'is_active' => true,
                'sort_order' => 4,
                'description' => 'Your current life stage',
                'options' => ['Highschool', 'University student', 'Young profession', 'Senior Professional'],
            ],
            [
                'name' => 'About you',
                'short_name' => 'about_you',
                'input_type' => 'textarea',
                'required' => true,
                'is_active' => true,
                'sort_order' => 5,
                'description' => 'Tell us about yourself. Write in one or two sentences about your intersets, prefernces or anything you deem relevant',
                'options' => [],
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
