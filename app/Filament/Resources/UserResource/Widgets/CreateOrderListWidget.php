<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;

class CreateOrderListWidget extends BaseWidget
{
    protected static ?string $model = Order::class;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(Order::latest()->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('id')
                    ->label('Order ID')
                    ->sortable(), 
                TextColumn::make('pickup_date')
                    ->formatStateUsing(fn ($record) => "Date: "."{$record->booking->pickup_date}".'<br>ID: '."{$record->series_no}")

                    ->html()
                    ->label('Date & Time')   
                    ->sortable(), 
                TextColumn::make('user_id')
                    ->label('Assigned To')   
                    ->html()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')   
                    ->html()
                    ->sortable(),              
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('View'),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
