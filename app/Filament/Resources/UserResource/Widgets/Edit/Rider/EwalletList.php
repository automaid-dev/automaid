<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Rider;

use App\Models\Commission; 
use App\Models\CommissionTransaction; 
use App\Models\Role;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class EwalletList extends BaseWidget
{
    public ?int $userId = null;

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(CommissionTransaction::whereHas('commission', function($query) {
                $query->where('user_id', $this->userId);
            })->with(['commission', 'order'])->latest())
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('order_id')
                    ->searchable()
                    ->sortable()                    
                    ->label('Order ID'),
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->sortable(),
                TextColumn::make('final_amount')
                    ->label('Total Amount (RM)')
                    ->formatStateUsing(function ($state) {
                        return number_format((float) $state, 2);
                    })
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type/Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn(string $state): string => match ($state) {
                        'earned' => 'info',
                        'transferred' => 'success',
                    })
                    ->sortable(),              
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(false)
                    ->placeholder('All Status')
                    ->options([
                        CommissionTransaction::EARNED => 'EARNED',
                        CommissionTransaction::TRANSFERRED => 'TRANSFERRED',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('date')->label(''),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date'], fn ($q) => $q->whereDate('created_at', $data['date']));
                    })
                    ->label(false),
            ], layout: FiltersLayout::AboveContent)
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
