<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Qrcode;

class QrcodeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'status' => 'pending',
                'created_by' => 1,
            ],
            [
                'status' => 'pending',
                'created_by' => 1,
            ],
            [
                'status' => 'pending',
                'created_by' => 1,
            ],
            [
                'status' => 'pending',
                'created_by' => 1,
            ],
        ];
        foreach ($datas as $data) {
            $qrcode = new Qrcode();
            $data['series_no'] = $qrcode->getNextSeriesNo();
            Qrcode::updateOrCreate($data);
        }
    }
}
