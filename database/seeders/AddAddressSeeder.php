<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Address;

class AddAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'user_id' => 45,
                'unit_no' => 'sdfd',
                'floor' => 'sdfd',
                'block' => 'sdfd',
                'address_line_1' => 'sdfd',
                'address_line_2' => 'sdfd',
                'address_line_3' => 'sdfd',
                'postcode' => 'sdfd',
                'city' => 'sdfd',
                'state_id' => 13,
                'country_id' => 133,
                'address_title' => 'home',
                'status' => 'active'
            ],
        ];
        foreach ($datas as $data) {
            Address::updateOrCreate($data);
        }
    }
}
