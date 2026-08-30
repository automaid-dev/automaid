<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCoverageResource\Pages;
use App\Models\City;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms\Form;

class ServiceCoverageResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Coverage — Cities';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'city coverage';

    /**
     * No create/edit form — cities are seeded reference data, not
     * something admin adds/removes here. This resource exists purely
     * to toggle the one is_service_covered flag per city.
     */
    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(City::query()->with('state'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state.name')
                    ->label('State')
                    ->sortable(),
                ToggleColumn::make('is_service_covered')
                    ->label('Covered'),
            ])
            ->filters([
                SelectFilter::make('state_id')
                    ->label('State')
                    ->relationship('state', 'name'),
                TernaryFilter::make('is_service_covered')->label('Covered'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCoverage::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
