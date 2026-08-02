<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bank;

class AddOtherNewBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'name' => 'Ryt Bank',
                'code' => '74',
            ],
            [
                'name' => 'Touch n Go eWallet',
                'code' => '75',
            ],
        ];
        foreach ($datas as $data) {
            Bank::updateOrCreate($data);
        }
    }
}
