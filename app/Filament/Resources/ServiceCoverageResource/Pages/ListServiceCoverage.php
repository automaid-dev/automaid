<?php

namespace App\Filament\Resources\ServiceCoverageResource\Pages;

use App\Filament\Resources\ServiceCoverageResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceCoverage extends ListRecords
{
    protected static string $resource = ServiceCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
