<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@genify.ai'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('admin1234'),
                'is_admin' => true,
                'status' => 'active',
                'credits' => 1000,
            ]
        );
    }
}
