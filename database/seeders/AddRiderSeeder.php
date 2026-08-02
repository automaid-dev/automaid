<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Arr;

class AddRiderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Rider 1',
                'email' => 'rider1@admin.com',
                'mobile_no' => '0123234324',
                'password' => bcrypt('11111111'),
                'email_verified_at' => now(),
                'status' => User::ACTIVE,
                'is_active' => true,
            ],
            [
                'name' => 'Rider 2',
                'email' => 'rider2@admin.com',
                'mobile_no' => '0123234324',
                'password' => bcrypt('11111111'),
                'email_verified_at' => now(),
                'status' => User::ACTIVE,
                'is_active' => true,
            ],
            [
                'name' => 'Rider 3',
                'email' => 'rider3@admin.com',
                'mobile_no' => '0123234324',
                'password' => bcrypt('11111111'),
                'email_verified_at' => now(),
                'status' => User::ACTIVE,
                'is_active' => true,
            ],
        ];
        foreach ($users as $user) {

        }
    }
}
