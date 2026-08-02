<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Commission;
use App\Models\CommissionTransaction;
use Carbon\Carbon;

class ListPaid extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    public ?Commission $record = null;

    /**
     * [mount description]
     * @return [type] [description]
     */
    public function mount(): void
    {
        parent::mount();
        $this->record = request()->route('record');
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record?->user->name ?? 'Unknown';
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                CommissionTransaction::query()
                    ->where('commission_id', $this->record->id)
            )
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No'), 
                TextColumn::make('order_id')
                    ->searchable()
                    ->label('Order ID'),
                TextColumn::make('order.created_at')
                    ->label('Order Date')
                    ->formatStateUsing(fn ($state) => $state->format('d M Y')),
                TextColumn::make('paid_at')
                    ->label('Payout Date')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d M Y') : '-'),
                TextColumn::make('final_amount')
                    ->label('Payout Amount (RM)'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'primary',
                })
                ->sortable()
                ->label('Payout Status'),
            ])
            ->actions([])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}