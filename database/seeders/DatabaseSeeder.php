<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create sample csv file with 5000 rows
        $path = storage_path('app/imports');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = $path . '/products_5000.csv';
        $handle = fopen($filename, 'w');

        // Header CSV
        fputcsv($handle, ['name', 'sku', 'price', 'stock']);

        $brands = ['Apple', 'Samsung', 'Dell', 'Sony', 'Asus', 'HP', 'Lenovo', 'Razer', 'Logitech', 'Microsoft'];
        $products = ['Laptop', 'Smartphone', 'Monitor', 'Keyboard', 'Mouse', 'Headphone', 'Tablet'];

        for ($i = 1; $i <= 5000; $i++) {
            $brand = $brands[array_rand($brands)];
            $product = $products[array_rand($products)];

            $name = "$brand $product $i";
            $sku = strtoupper(substr($brand, 0, 3))
                . '-' . strtoupper(substr($product, 0, 3))
                . '-' . str_pad($i, 5, '0', STR_PAD_LEFT);

            $price = rand(1_500_000, 30_000_000);
            $stock = rand(1, 500);

            fputcsv($handle, [
                $name,
                $sku,
                $price,
                $stock
            ]);
        }

        fclose($handle);

        $this->command->info("CSV generated successfully: storage/app/imports/products_500.csv");
    }
}