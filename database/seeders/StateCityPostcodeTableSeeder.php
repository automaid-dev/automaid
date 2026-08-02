<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\State;
use App\Models\City;
use App\Models\Postcode;

class StateCityPostcodeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = 'https://raw.githubusercontent.com/AsyrafHussin/malaysia-postcodes/main/all.json';
        $response = Http::get($url);
        $contents = json_decode($response, true);

        foreach ($contents['state'] as $data) {
            $state = State::updateOrCreate([
                'name' => $data['name'],
                'country_code' => 'MY',                
            ]);
            if (count($data['city']) > 0) {
                foreach ($data['city'] as $data_city) {
                    $city = City::updateOrCreate([
                        'name' => $data_city['name'],
                        'state_id' => $state->id,                
                    ]);
                    if ($data_city['postcode'] && count($data_city['postcode']) > 0) {
                        foreach ($data_city['postcode'] as $key => $value) {
                            Postcode::updateOrCreate([
                                'postcode' => $value,
                                'city_id' => $city->id,                
                            ]);
                        }
                    }
                }
            }

        }
    }
}
