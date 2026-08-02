<?php

namespace App\Filament\Resources\BagResource\Pages;

use App\Filament\Resources\BagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBags extends ListRecords
{
    protected static string $resource = BagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Bag Purchased';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Bag Purchased'; // Custom title        
    }
}
