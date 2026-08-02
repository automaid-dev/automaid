<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Customer;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Qrcode;
use Filament\Tables\Columns\TextColumn;

class BagList extends BaseWidget
{
    protected static ?string $heading = '';

    public ?int $userId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(Qrcode::latest()
                ->where('user_id', $this->userId)
                ->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->label('Serial Number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('scan_at')
                    ->label('Scanned On')   
                    ->html()
                    ->sortable(), 
                
                TextColumn::make('created_at')
                    ->label('Order Date')   
                    ->html()
                    ->sortable(),            
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
