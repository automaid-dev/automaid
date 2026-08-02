<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Arr;

class AddMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Merchant',
                'email' => 'merchant@admin.com',
                'mobile_no' => '0194563904',
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
            $admin->assignRole('merchant');
        }
    }
}
