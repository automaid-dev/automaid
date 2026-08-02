<?php

namespace App\Filament\Resources\SettingResource\Widgets;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\AddOnResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use App\Models\AddOn; 

class AddonList extends BaseWidget
{
    protected static ?string $model = Activity::class;

    protected static ?string $heading = 'Add-Ons';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(AddOn::latest()->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('title')
                    ->label('Title')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Price (RM)')
                    ->formatStateUsing(function ($state) {
                        return number_format((float) $state, 2);
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created at')
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
            ->recordUrl(fn ($record) => AddOnResource::getUrl('edit-addon', ['record' => $record]))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add new')
                    ->url(SettingResource::getUrl('create-addon'))
                    ->openUrlInNewTab(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->url(fn ($record) => AddOnResource::getUrl('edit-addon', ['record' => $record]))
                    ->openUrlInNewTab(false),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
