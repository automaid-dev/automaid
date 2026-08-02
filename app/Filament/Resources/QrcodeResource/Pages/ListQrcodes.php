<?php

namespace App\Filament\Resources\QrcodeResource\Pages;

use App\Filament\Resources\QrcodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQrcodes extends ListRecords
{
    protected static string $resource = QrcodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
