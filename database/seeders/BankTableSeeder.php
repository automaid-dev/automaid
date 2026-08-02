<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Bank;

class BankTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = 'https://gist.githubusercontent.com/zulhfreelancer/acf1abd1d0e22a0b59c9a51715c89b1e/raw/89cf373fe13743700f5ab611b86427853a21cf83/malaysia_banks.json';
        $response = Http::get($url);
        $contents = json_decode($response, true);
        foreach ($contents as $data) {
            Bank::updateOrCreate([
                'name' => $data['name'],
                'code' => $data['code'],                
            ]);
        }
    }
}
