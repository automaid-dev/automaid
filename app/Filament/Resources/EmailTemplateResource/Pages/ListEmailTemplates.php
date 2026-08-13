<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        // No create action — templates are a fixed, seeded set of keys
        // the app looks up by name, not a free-form list.
        return [];
    }
}
