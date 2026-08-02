<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Arr;

class AddAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'superadmin@admin.com',
                'mobile_no' => '012345678',
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
            $admin->assignRole('admin');
        }
    }
}
