<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class EwalletList extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                // ...
            )
            ->columns([
                // ...
            ]);
    }
}
