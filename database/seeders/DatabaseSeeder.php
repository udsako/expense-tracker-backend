<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed a specific Admin user
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@expensetracker.com',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        // 2. Seed sample transactions for the Admin
        $admin->expenses()->createMany([
            [
                'title' => 'Monthly Salary',
                'amount' => 250000,
                'type' => 'income',
                'category' => 'Salary',
                'date' => now()->subDays(5)->toDateString(),
            ],
            [
                'title' => 'Grocery Shopping',
                'amount' => 18500,
                'type' => 'expense',
                'category' => 'Food',
                'date' => now()->subDays(2)->toDateString(),
            ],
            [
                'title' => 'Internet Subscription',
                'amount' => 12000,
                'type' => 'expense',
                'category' => 'Utilities',
                'date' => now()->toDateString(),
            ],
        ]);

        // 3. Email / Factory Seeding: Generate 5 random regular users with fake emails
        User::factory(5)->create();
    }
}
