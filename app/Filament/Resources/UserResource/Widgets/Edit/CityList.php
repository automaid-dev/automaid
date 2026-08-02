<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit;

use App\Models\CityUser;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\ComponentContainer;

class CityList extends BaseWidget
{
    protected static ?string $heading = 'Active City Covered';

    public ?int $userId = null;

    public function getId(): string
    {
        return 'city-list-' . $this->userId;
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(function() {
                $userId = $this->userId;
                return CityUser::latest()
                    ->where('user_id', $userId)
                    ->limit(10);
            })
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable(), 
                TextColumn::make('city.state.name')
                    ->label('State')
                    ->sortable(), 
                TextColumn::make('created_at')
                    ->date()
                    ->label('Created At')
                    ->sortable(), 
            ])
            ->headerActions([
                Action::make('add_city')
                    ->label('Add New City')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->modalWidth('md')
                    ->form([
                        Grid::make(1)
                            ->schema([
                            	Select::make('state_id')
                            	    ->label('State')
                            	    ->searchable()
                            	    ->placeholder('Select State')
                            	    ->preload()
                            	    ->required()
                            	    ->options(\App\Models\State::orderBy('name')->pluck('name', 'id')->toArray())
                            	    ->reactive()
                            	    ->afterStateUpdated(function (callable $set) {
                            	        // Clear the city field whenever state changes
                            	        $set('city_id', null);
                            	    }),

                            	Select::make('city_id')
                            	    ->label('City')
                            	    ->searchable()
                            	    ->required()
                            	    ->placeholder('Select City')
                            	    ->options(function (callable $get) {
                            	        $stateId = $get('state_id');
                            	        return $stateId
                            	            ? \App\Models\City::where('state_id', $stateId)->orderBy('name')->pluck('name', 'id')->toArray()
                            	            : [];
                            	    }),

                            ]),
                    ])
                    ->action(function (array $data): void {
                        $data['user_id'] = $this->userId; 
                        $data['created_by'] = auth()->user()->id;
                        \App\Models\CityUser::create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->modalWidth('sm')
                    ->modal('edit')
                    ->modalHeading('Edit City Coverage')
                    ->mountUsing(function (ComponentContainer $form, $record) {
                        $form->fill([
                            'state_id' => $record->city?->state_id, // 🔥 from cities table
                            'city_id'  => $record->city_id,
                        ]);
                    })
                    ->form([
                        Grid::make(1)->schema([
                            Select::make('state_id')
                                ->label('State')
                                ->searchable()
                                ->preload()
                                ->options(
                                    \App\Models\State::orderBy('name')->pluck('name', 'id')->toArray()
                                )
                                ->reactive()
                                ->afterStateUpdated(fn (callable $set) => $set('city_id', null))
                                ->required(),

                            Select::make('city_id')
                                ->label('City')
                                ->searchable()
                                ->reactive()
                                ->options(fn (callable $get) =>
                                    $get('state_id')
                                        ? \App\Models\City::where('state_id', $get('state_id'))
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->toArray()
                                        : []
                                )
                                ->required(),
                        ]),
                    ])                    
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'city_id' => $data['city_id'],
                            'updated_by' => auth()->user()->id
                        ]);
                    })
                    ->extraAttributes(fn ($record) => [
                        'wire:key' => 'edit-city-' . $record->id, // unique per record
                    ]),

                Tables\Actions\DeleteAction::make()
                    ->modal('delete')
                    ->label('')
                    ->extraAttributes(fn ($record) => [
                        'wire:key' => 'delete-city-' . $record->id, // unique per record
                    ]),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

}



