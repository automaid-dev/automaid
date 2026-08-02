<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AddOn;

class AddOnTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'title' => 'Folding Services',
                'price' => '20',
                'status' => 'ACTIVE',
                'created_by' => 1,
            ],
            [
                'title' => 'Steam Iron',
                'price' => '45',
                'status' => 'ACTIVE',
                'created_by' => 1,
            ],
        ];
        foreach ($datas as $data) {
            AddOn::updateOrCreate($data);
        }
    }
}
