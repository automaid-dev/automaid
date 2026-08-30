<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StateCoverageResource\Pages;
use App\Models\State;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms\Form;

class StateCoverageResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Coverage — States';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'state coverage';

    /**
     * No create/edit form — states are seeded reference data, not
     * something admin adds/removes here. This resource exists purely
     * to toggle the one is_service_covered flag per state.
     */
    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(State::query())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_service_covered')
                    ->label('Covered')
                    ->afterStateUpdated(function () {
                        \Filament\Notifications\Notification::make()
                            ->title('Turning this on covers every city in this state, regardless of each city\'s own toggle on the Cities page.')
                            ->info()
                            ->send();
                    }),
            ])
            ->filters([
                TernaryFilter::make('is_service_covered')->label('Covered'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStateCoverage::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
