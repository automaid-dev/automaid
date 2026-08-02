<?php

namespace App\Filament\Resources\WaitingListResource\Pages;

use App\Filament\Resources\WaitingListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListWaitingLists extends ListRecords
{
    protected static string $resource = WaitingListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportAllCsv')
                ->label('Export ALL to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {

                    $records = \App\Models\WaitingList::with('city')
                        ->get(['name', 'email', 'mobile_no', 'postcode', 'city_id']);

                    return response()->streamDownload(function () use ($records) {

                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Name', 'Email', 'Mobile No', 'City', 'Postcode']);

                        foreach ($records as $row) {
                            fputcsv($handle, [
                                $row->name ?? null,
                                $row->email ?? null,
                                $row->mobile_no ?? null,
                                $row->city?->name ?? null,
                                $row->postcode ?? null,
                            ]);
                        }

                        fclose($handle);

                    }, 'waiting-list-all.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }

}
