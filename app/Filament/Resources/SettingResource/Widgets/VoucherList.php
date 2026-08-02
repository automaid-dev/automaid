<?php

namespace App\Filament\Resources\SettingResource\Widgets;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\VoucherResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use App\Models\Voucher;

class VoucherList extends BaseWidget
{
    protected static ?string $model = Activity::class;

    protected static ?string $heading = 'Voucher & Discount';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(Voucher::latest()->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('code')
                    ->label('Voucher Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_type')
                    ->formatStateUsing(fn ($state) => $state == 1 ? 'RM' : '%')
                    ->label('Discount Type')
                    ->sortable(),
                TextColumn::make('discount_amount')
                    ->label('Discount Amount')
                    ->formatStateUsing(function ($state) {
                        return number_format((float) $state, 2);
                    })
                    ->sortable(),
                TextColumn::make('usage_limit')
                    ->label('Usage Limit')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'inactive' => 'danger',
                    })
                    ->sortable(),              
            ])
            ->recordUrl(fn ($record) => VoucherResource::getUrl('edit-voucher', ['record' => $record]))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->label('Add new')
                ->url(SettingResource::getUrl('create-voucher'))
                ->openUrlInNewTab(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->label('')
                ->url(fn ($record) => VoucherResource::getUrl('edit-voucher', ['record' => $record]))
                ->openUrlInNewTab(false),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
