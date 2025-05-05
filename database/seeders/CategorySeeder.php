<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Create main categories
        $categories = [
            'Beverages',
            'Food',
            'Electronics',
            'Clothing',
            'Household',
            'Personal Care',
            'Services',
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
                'is_active' => true,
            ]);
        }

        // Create subcategories
        $subcategories = [
            'Beverages' => ['Coffee', 'Tea', 'Soft Drinks', 'Juice', 'Water'],
            'Food' => ['Snacks', 'Bread', 'Dairy', 'Meat', 'Fruits', 'Vegetables'],
            'Electronics' => ['Smartphones', 'Accessories', 'Computers', 'Peripherals'],
            'Clothing' => ['Men', 'Women', 'Kids', 'Accessories'],
            'Household' => ['Cleaning', 'Kitchen', 'Bathroom', 'Furniture'],
            'Personal Care' => ['Hair Care', 'Skin Care', 'Oral Care', 'Cosmetics'],
            'Services' => ['Repair', 'Installation', 'Maintenance', 'Consulting'],
        ];

        foreach ($subcategories as $parent => $children) {
            $parentCategory = Category::where('name', $parent)->first();

            if ($parentCategory) {
                foreach ($children as $child) {
                    Category::create([
                        'name' => $child,
                        'parent_id' => $parentCategory->id,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
