<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Order;

class LatestOrdersTable extends BaseWidget
{
    protected static ?string $heading = 'Latest Orders';

    protected int|string|array $columnSpan = [
        'default' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::where('order_type', Order::BOOKING)->latest()->limit(10))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d M Y'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('series_no')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(), 
                TextColumn::make('customer_latest_status.status.desc')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('Amount (RM)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ]);
    }
}
