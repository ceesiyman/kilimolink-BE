<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('products')->insert([
            [
                'name' => 'Tomato',
                'image' => 'productImages/tomato.jpg',
                'description' => 'Fresh red tomatoes.',
                'price' => 2.50,
                'category_id' => 1,
                'is_featured' => true,
                'user_id' => 1,
                'stock' => 100,
                'location' => 'Farm A',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Banana',
                'image' => 'productImages/banana.jpg',
                'description' => 'Sweet bananas.',
                'price' => 1.20,
                'category_id' => 2,
                'is_featured' => false,
                'user_id' => 1,
                'stock' => 200,
                'location' => 'Farm B',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rice',
                'image' => 'productImages/rice.jpg',
                'description' => 'Organic white rice.',
                'price' => 0.90,
                'category_id' => 3,
                'is_featured' => true,
                'user_id' => 1,
                'stock' => 500,
                'location' => 'Farm C',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
