<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('categories')->insert([
            [
                'name' => 'Vegetables',
                'description' => 'Fresh and organic vegetables.'
            ],
            [
                'name' => 'Fruits',
                'description' => 'Seasonal and tropical fruits.'
            ],
            [
                'name' => 'Grains',
                'description' => 'Rice, wheat, maize, and more.'
            ],
            [
                'name' => 'Legumes',
                'description' => 'Beans, lentils, peas, and more.'
            ],
            [
                'name' => 'Roots & Tubers',
                'description' => 'Potatoes, yams, cassava, etc.'
            ],
        ]);
    }
}
