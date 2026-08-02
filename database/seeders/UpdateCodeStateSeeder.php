<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\State;

class UpdateCodeStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = State::get();
        foreach ($states as $state) {
            if ($state->id == 1) {
                $state->code = '14';
                $state->save();
            }
            if ($state->id == 2) {
                $state->code = '01';
                $state->save();
            }
            if ($state->id == 3) {
                $state->code = '02';
                $state->save();
            }
            if ($state->id == 4) {
                $state->code = '03';
                $state->save();
            }
            if ($state->id == 5) {
                $state->code = '04';
                $state->save();
            }
            if ($state->id == 6) {
                $state->code = '05';
                $state->save();
            }
            if ($state->id == 7) {
                $state->code = '06';
                $state->save();
            }
            if ($state->id == 8) {
                $state->code = '07';
                $state->save();
            }
            if ($state->id == 9) {
                $state->code = '08';
                $state->save();
            }
            if ($state->id == 10) {
                $state->code = '09';
                $state->save();
            }
            if ($state->id == 11) {
                $state->code = '12';
                $state->save();
            }
            if ($state->id == 12) {
                $state->code = '13';
                $state->save();
            }
            if ($state->id == 13) {
                $state->code = '10';
                $state->save();
            }
            if ($state->id == 14) {
                $state->code = '11';
                $state->save();
            }
            if ($state->id == 15) {
                $state->code = '15';
                $state->save();
            }
            if ($state->id == 16) {
                $state->code = '16';
                $state->save();
            }


        }
    }
}
