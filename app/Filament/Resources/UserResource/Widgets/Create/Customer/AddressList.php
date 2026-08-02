<?php

namespace App\Filament\Resources\UserResource\Widgets\Create\Customer;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Address;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Session;

class AddressList extends BaseWidget
{
    protected static ?string $heading = 'Lists of Address';

    public function table(Table $table): Table
    {
        return $table
            ->query(Address::latest()
                ->where('user_id', auth()->id())
                ->limit(10))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('address_title')
                    ->label('Address Title')
                    ->sortable(), 
                TextColumn::make('unit_no')
                    ->label('Address')   
                    ->formatStateUsing(fn ($record) => "{$record->unit_no}" . ' ' . $record->floor . ' ' . $record->block . ' ' . $record->address_line_1 . ' ' . $record->address_line_2)
                    ->html()
                    ->sortable(), 
                TextColumn::make('created_at')
                    ->date()
                    ->label('Created At')
                    ->sortable(), 
            ])
            ->headerActions([
                Action::make('add_address')
                    ->label('Add New Address')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('address_title')->label('Address Title')->placeholder('e.g My Home'),
                                TextInput::make('unit_no')->label('House No./Unit No.')->placeholder('e.g H-9-2'),
                                TextInput::make('floor')->label('Floor/Level (if any)')->placeholder('e.g Level 2'),
                                TextInput::make('block')->label('Block/Building (if any)')->placeholder('e.g Block H'),
                                TextInput::make('address_line_1')->label('Address Line 1')->placeholder('e.g No. 123, Jalan PP22'),
                                TextInput::make('address_line_2')->label('Address Line 2 (if any)')->placeholder('e.g Taman Equine'),
                                Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->searchable()
                                    ->placeholder('Select Country')
                                    ->preload(),
                                TextInput::make('postcode')->label('Postcode')->placeholder('e.g 43300'),
                                Select::make('state_id')
                                    ->label('State')
                                    ->relationship('state', 'name')
                                    ->searchable()
                                    ->placeholder('Select State')
                                    ->preload(),
                                TextInput::make('city')->label('City')->placeholder('e.g Seri Kembangan'),
                            ]),
                    ])
                    ->action(function (array $data): void {
                        Session::put('data_create_address', $data);
                        // \App\Models\Address::create($data);

                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }
}
