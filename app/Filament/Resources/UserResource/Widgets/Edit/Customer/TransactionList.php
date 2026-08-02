<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Customer;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionList extends BaseWidget
{
    protected static ?string $heading = '';

    protected static ?string $model = Transaction::class;
    
    public ?int $userId = null;

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::whereHas('order', function($query) {
                $query->where('user_id', $this->userId);
            })
            ->latest()->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('order.id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('id')
                    ->label('Transaction ID')
                    ->sortable()
                    ->searchable(),                      
                TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        $labels = [
                            Transaction::PURCHASE_BAG => 'BAG PURCHASE',
                            Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
                            Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
                            Transaction::BOOKING => 'BOOKING',
                        ];
                        return $labels[$state] ?? strtoupper(str_replace('_', ' ', $state));
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Transaction::PURCHASE_BAG => 'info',       // blue
                        Transaction::SUBSCRIPTION => 'success',     // green
                        Transaction::SUBSCRIPTION_RENEWAL => 'warning', // yellow
                        Transaction::BOOKING => 'gray',             // neutral
                        default => 'secondary',                     // fallback
                    }),
                TextColumn::make('order.quantity')
                    ->label('Quantity')   
                    ->html()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Total Amount (RM)')   
                    ->html()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status') 
                    ->badge()  
                    ->html()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),              
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\TransactionResource::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\TransactionResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->modal(false),

            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }



}
