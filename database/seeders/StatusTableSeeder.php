<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            ['code' => '01', 'desc' => 'Waiting rider for pickup', 'created_by' => 1],
            ['code' => '11', 'desc' => 'Pending for acceptance', 'created_by' => 1],
            ['code' => '21', 'desc' => 'Pending for acceptance', 'created_by' => 1],
            ['code' => '12', 'desc' => 'Ready for pickup', 'created_by' => 1],
            ['code' => '22', 'desc' => 'Awaiting bag delivery', 'created_by' => 1],
            ['code' => '13', 'desc' => 'Delivery to wash outlet', 'created_by' => 1],
            ['code' => '02', 'desc' => 'Delivery to wash outlet', 'created_by' => 1],
            ['code' => '23', 'desc' => 'Wash in progress', 'created_by' => 1],
            ['code' => '03', 'desc' => 'Wash in progress', 'created_by' => 1],
            ['code' => '24', 'desc' => 'Wash completed', 'created_by' => 1],
            ['code' => '14', 'desc' => 'Pickup from wash outlet', 'created_by' => 1],
            ['code' => '04', 'desc' => 'Delivery to customer', 'created_by' => 1],
            ['code' => '15', 'desc' => 'Delivery to customer', 'created_by' => 1],
            ['code' => '25', 'desc' => 'Order picked up', 'created_by' => 1],
            ['code' => '16', 'desc' => 'Order delivered', 'created_by' => 1],
            ['code' => '26', 'desc' => 'Order delivered', 'created_by' => 1],
            ['code' => '05', 'desc' => 'Order delivered', 'created_by' => 1],
        ];
        foreach ($datas as $data) {
            Status::updateOrCreate($data);
        }
    }
}
