<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Country;

class CountryTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = 'https://gist.githubusercontent.com/almost/7748738/raw/575f851d945e2a9e6859fb2308e95a3697bea115/countries.json';
        $response = Http::get($url);
        $contents = json_decode($response, true);
        foreach ($contents as $data) {
            Country::updateOrCreate([
                'name' => $data['name'],
                'code' => $data['code'],                
            ]);
        }
    }
}
