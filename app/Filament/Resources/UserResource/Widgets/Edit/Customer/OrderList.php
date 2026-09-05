<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Customer;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Carbon\Carbon;

class OrderList extends BaseWidget
{
    protected static ?string $heading = '';

    public ?int $userId = null;

    protected static ?string $model = Order::class;
    
    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(Order::latest()
                ->where('user_id', $this->userId)  
                ->where('order_type', Order::BOOKING)              
                ->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(), 
                TextColumn::make('created_at')
                    ->formatStateUsing(function ($record) {
                        if (!$record->booking) {
                            return '-';
                        }
                        return Carbon::parse($record->booking->pickup_date)->format('d M Y') . ', ' .
                               Carbon::parse($record->booking->pickup_start_time)->format('H:i a');
                    })
                    ->label('Date & Time')   
                    ->sortable(), 
                TextColumn::make('user_id')
                    ->label('Assigned To')   
                    ->formatStateUsing(function ($record) {
                        $riderName = optional(optional($record->rider)->user)->name;
                        $merchantName = optional(optional($record->merchant)->user)->name;
                        if (!$riderName && !$merchantName) {
                            return '-';
                        }
                        return
                            ($riderName ? 'Rider: ' . $riderName : '') .
                            ($merchantName ? '<br>Merchant: ' . $merchantName : '');
                    })
                    ->html()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($record) {
                        if (!$record->customer_latest_status) {
                            return '-';
                        }
                        return $record->customer_latest_status->status->desc;
                    })
                    ->html()
                    ->badge()
                    ->sortable(),              
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.orders.edit', ['record' => $record]))
                    ->label('View'),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
