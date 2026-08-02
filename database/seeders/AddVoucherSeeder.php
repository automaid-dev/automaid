<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Voucher;

class AddVoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            ['code' => 'XX012', 'description' => 'Voucher 1', 'discount_amount' => '5.50', 'start_at' => '2024-01-01 00:00:01', 'expired_at' => '2050-01-01 01:01:01', 'status' => 'active'],
        ];
        foreach ($datas as $data) {
            Voucher::updateOrCreate($data);
        }
    }
}
