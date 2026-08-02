<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Announcement;

class AnnouncementTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datas = [
            [
                'title' => 'Announcement 1',
                'slug' => 'announcement-1',
                'status' => Announcement::ACTIVE,
            ],
            [
                'title' => 'Announcement 2',
                'slug' => 'announcement-2',
                'status' => Announcement::ACTIVE,
            ],

        ];
        foreach ($datas as $data) {
            Announcement::updateOrCreate($data);
        }
    }
}
