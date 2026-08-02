<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Color;

class AddColorVehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            ['color' => 'White'],
            ['color' => 'Black'],
            ['color' => 'Blue'],
            ['color' => 'Yellow'],
            ['color' => 'Red'],
            ['color' => 'Green'],
            ['color' => 'Brown'],
            ['color' => 'Orange'],
            ['color' => 'Pink'],
            ['color' => 'Other'],
        ];
        foreach ($datas as $data) {
            Color::updateOrCreate($data);
        }
    }
}
