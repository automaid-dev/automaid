<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class AddNewStatusRiderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $codes = [
            '17' => 'Awaiting wash to complete',
            '24' => 'Awaiting rider to pickup',
            '25' => 'Rider en route to customer',
        ];
        foreach ($codes as $code => $desc) {

            $status = Status::where('code', $code)->first();
            if (!$status) {
                $new = new Status();
                $new->code = $code;
                $new->desc = $desc;
                $new->created_by = 1;
                $new->save();
            }
            else {
                $status->desc = $desc;
                $status->save();
            }

        }

    }
}
