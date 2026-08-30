<?php

namespace App\Filament\Resources\StateCoverageResource\Pages;

use App\Filament\Resources\StateCoverageResource;
use Filament\Resources\Pages\ListRecords;

class ListStateCoverage extends ListRecords
{
    protected static string $resource = StateCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
