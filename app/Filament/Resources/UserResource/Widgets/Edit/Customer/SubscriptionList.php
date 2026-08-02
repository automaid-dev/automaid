<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Customer;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class SubscriptionList extends BaseWidget
{
    protected static ?string $heading = '';

    protected static ?string $model = Subscription::class;
    
    public ?int $userId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function() {
                $userId = $this->userId;
                return Subscription::latest()
                    ->where('user_id', $userId)
                    ->limit(10);
            })
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('id')
                    ->label('Subscription ID')
                    ->sortable()
                    ->searchable(), 
                TextColumn::make('created_at')
                    ->date()
                    ->label('Created At')
                    ->formatStateUsing(fn ($state) => $state?->format('d F Y, h:i A'))
                    ->sortable(), 
                // TextColumn::make('updated_at')
                TextColumn::make('renew_at')
                    ->date()
                    ->label('Renew At')
                    ->formatStateUsing(fn ($state) => $state?->format('d F Y, h:i A'))
                    ->sortable(), 
                TextColumn::make('status')
                    ->label('Status') 
                    ->badge()  
                    ->sortable()
                    ->formatStateUsing(fn(string $state) => ucfirst(strtolower($state)))
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        'inactive' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\SubscriptionResource::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\SubscriptionResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->modal(false),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

}



