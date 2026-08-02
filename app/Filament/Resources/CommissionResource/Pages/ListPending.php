<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Models\Transaction; // Dummy Data Just To Show The Table
use App\Filament\Resources\CommissionResource;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListPending extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    public function getTitle(): string
    {
        return 'Fatimah Zahra binti Ismail (Rider)';
    }
    
    public function getBreadcrumb(): string
    {
        return 'Fatimah Zahra binti Ismail (Rider)';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Transaction::query())
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('commission_amount')
                    ->label('Commission Amount (RM)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('payout_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'primary',
                })
                ->sortable()
                ->label('Payout Status'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->action(function ($record) {})
                    ->modalHeading('Update Commission Amount')
                    ->modalWidth('lg')
                    ->modalButton('Save')
                    ->form([
                        TextInput::make('commission_amount')
                            ->label('Commission Amount (RM)*')
                            ->required()
                            ->numeric()
                            ->default(fn ($record) => $record->commission_amount),
                    ])
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}