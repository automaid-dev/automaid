<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'rider_commission' => '10',
                'merchant_commission' => '20',
                'wash_fee' => '20',
                'bag_price' => '10',
                'delivery_price' => '10',
                'created_by' => 1,
            ],
        ];
        foreach ($datas as $data) {
            Setting::updateOrCreate($data);
        }
    }
}
