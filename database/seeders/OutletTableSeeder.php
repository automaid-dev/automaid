<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Outlet;

class OutletTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'name' => 'Dobi Queen',
                'slug' => 'dobi-queen',
                'address_line_1' => 'No 1',
                'address_line_2' => '14/13 Lorong 1',
                'address_line_3' => 'Taman Perdana 2',
                'postcode' => '55100',
                'city' => 'Kuala Lumpur',
                'state_id' => 13,
                'country_id' => 133,
                'status' => Outlet::ACTIVE,
            ],
            [
                'name' => 'Laundry Bar',
                'slug' => 'laundry-bar',
                'address_line_1' => 'No 1',
                'address_line_2' => '14/13 Lorong 1',
                'address_line_3' => 'Taman Perdana 2',
                'postcode' => '55100',
                'city' => 'Seri Kembangan',
                'state_id' => 13,
                'country_id' => 133,
                'status' => Outlet::ACTIVE,
            ],
            [
                'name' => 'Easy Wash',
                'slug' => 'easy-wash',
                'address_line_1' => 'No 1',
                'address_line_2' => '14/13 Lorong 1',
                'address_line_3' => 'Taman Perdana 2',
                'postcode' => '55100',
                'city' => 'Batu Caves',
                'state_id' => 13,
                'country_id' => 133,
                'status' => Outlet::ACTIVE,
            ],
            [
                'name' => 'Dobi Layan Diri',
                'slug' => 'dobi-layan-diri',
                'address_line_1' => 'No 1',
                'address_line_2' => '14/13 Lorong 1',
                'address_line_3' => 'Taman Perdana 2',
                'postcode' => '55100',
                'city' => 'Klang',
                'state_id' => 13,
                'country_id' => 133,
                'status' => Outlet::ACTIVE,
            ],
            [
                'name' => 'Dobi Putra',
                'slug' => 'dobi-putra',
                'address_line_1' => 'No 1',
                'address_line_2' => '14/13 Lorong 1',
                'address_line_3' => 'Taman Perdana 2',
                'postcode' => '55100',
                'city' => 'Gombak',
                'state_id' => 13,
                'country_id' => 133,
                'status' => Outlet::ACTIVE,
            ],

        ];
        foreach ($datas as $data) {
            Outlet::updateOrCreate($data);
        }
    }
}
