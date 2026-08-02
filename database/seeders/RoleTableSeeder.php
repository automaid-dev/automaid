<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'display_name' => 'SUPER ADMINISTRATOR', 'guard_name' => 'web'],
            ['name' => 'admin', 'display_name' => 'ADMIN', 'guard_name' => 'web'],
            ['name' => 'customer', 'display_name' => 'CUSTOMER', 'guard_name' => 'web'],
            ['name' => 'rider', 'display_name' => 'RIDER', 'guard_name' => 'web'],
            ['name' => 'merchant', 'display_name' => 'MERCHANT', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate($role);
        }
    }
}
