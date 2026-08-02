<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Qrcode;
use Filament\Tables\Columns\TextColumn;

class CreateBagListWidget extends BaseWidget
{
    protected static ?string $heading = 'Qrcode';

    public function table(Table $table): Table
    {
        return $table
            ->query(Qrcode::latest()->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->label('Serial Number')
                    ->sortable(), 

                TextColumn::make('scan_at')
                    ->label('Scanned On')   
                    ->html()
                    ->sortable(), 
                               
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
