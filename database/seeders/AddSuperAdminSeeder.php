<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Arr;

class AddSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@admin.com',
                'mobile_no' => '015656333',
                'password' => bcrypt('11111111'),
                'email_verified_at' => now(),
                'status' => User::ACTIVE,
                'is_active' => true,
            ],
        ];
        foreach ($users as $user) {
            $admin = User::updateOrCreate(
                [
                    'email' => $user['email']
                ],
                Arr::except($user, ['email'])
            );
            $admin->assignRole('super_admin');
        }
    }
}
