<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            // Concessions
            [
                'name' => 'Water',
                'description' => 'Bottled water',
                'price' => 150, // $1.50
                'category' => ProductCategory::Concession,
                'is_active' => true,
            ],
            [
                'name' => 'Soda',
                'description' => 'Canned soda (assorted)',
                'price' => 200, // $2.00
                'category' => ProductCategory::Concession,
                'is_active' => true,
            ],
            [
                'name' => 'Chips',
                'description' => 'Bag of chips (assorted)',
                'price' => 100, // $1.00
                'category' => ProductCategory::Concession,
                'is_active' => true,
            ],
            [
                'name' => 'Candy Bar',
                'description' => 'Chocolate candy bar (assorted)',
                'price' => 150, // $1.50
                'category' => ProductCategory::Concession,
                'is_active' => true,
            ],
            [
                'name' => 'Energy Drink',
                'description' => 'Energy drink (assorted)',
                'price' => 300, // $3.00
                'category' => ProductCategory::Concession,
                'is_active' => true,
            ],

            // Merchandise
            [
                'name' => 'T-Shirt',
                'description' => 'Corvallis Music Collective t-shirt',
                'price' => 2000, // $20.00
                'category' => ProductCategory::Merchandise,
                'sku' => 'TSHIRT-001',
                'is_active' => true,
            ],
            [
                'name' => 'Sticker',
                'description' => 'CMC logo sticker',
                'price' => 300, // $3.00
                'category' => ProductCategory::Merchandise,
                'sku' => 'STICKER-001',
                'is_active' => true,
            ],
            [
                'name' => 'Tote Bag',
                'description' => 'Reusable tote bag with CMC logo',
                'price' => 1500, // $15.00
                'category' => ProductCategory::Merchandise,
                'sku' => 'TOTE-001',
                'is_active' => true,
            ],
            [
                'name' => 'Hat',
                'description' => 'Adjustable snapback hat with logo',
                'price' => 2500, // $25.00
                'category' => ProductCategory::Merchandise,
                'sku' => 'HAT-001',
                'is_active' => true,
            ],
            [
                'name' => 'Patch',
                'description' => 'Iron-on embroidered patch',
                'price' => 500, // $5.00
                'category' => ProductCategory::Merchandise,
                'sku' => 'PATCH-001',
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }
    }
}
