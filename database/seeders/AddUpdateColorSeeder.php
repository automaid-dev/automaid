<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Color;

class AddUpdateColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // update color order
        $colors = Color::get();
        foreach ($colors as $color) {
            if ($color->color == 'Black') {
                $color->order = 1;
                $color->save();
            }
            if ($color->color == 'White') {
                $color->order = 2;
                $color->save();

            }
            if ($color->color == 'Red') {
                $color->order = 4;
                $color->save();

            }
            if ($color->color == 'Blue') {
                $color->order = 5;
                $color->save();

            }
            if ($color->color == 'Green') {
                $color->order = 6;
                $color->save();

            }
            if ($color->color == 'Yellow') {
                $color->order = 7;
                $color->save();

            }
            if ($color->color == 'Brown') {
                $color->order = 8;
                $color->save();

            }
            if ($color->color == 'Orange') {
                $color->order = 9;
                $color->save();

            }
            if ($color->color == 'Pink') {
                $color->order = 10;
                $color->save();

            }
            if ($color->color == 'Other') {
                $color->order = 23;
                $color->save();

            }

        }

        // insert new color
        $datas = [
            ['color' => 'Gray', 'order' => 3],
            ['color' => 'Purple', 'order' => 11],
            ['color' => 'Gold', 'order' => 12],
            ['color' => 'Silver', 'order' => 13],
            ['color' => 'Maroon', 'order' => 14],
            ['color' => 'Navy Blue', 'order' => 15],
            ['color' => 'Cyan/Light Blue', 'order' => 16],
            ['color' => 'Magenta', 'order' => 17],
            ['color' => 'Turquoise', 'order' => 18],
            ['color' => 'Olive Green', 'order' => 19],
            ['color' => 'Bronze', 'order' => 20],
            ['color' => 'Rose Gold', 'order' => 21],
            ['color' => 'Violet', 'order' => 22],
        ];
        foreach ($datas as $data) {
            Color::updateOrCreate($data);
        }
    }
}
