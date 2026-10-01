<?php

namespace App\Filament\Forms;

use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;

/**
 * Address fields + Google Maps pin picker for admin-created/edited riders
 * and merchants. Mirrors what the rider/merchant apps send at
 * registration (address lines, postcode, city, state, country, latitude,
 * longitude). Latitude/longitude are what the auto-assign job uses to
 * find the nearest rider/merchant, so a pin is required.
 *
 * $prefix is the form state prefix: '' on CreateUser (top-level fields),
 * 'rider.' / 'merchant.' on EditUser. $required is true on create; on
 * edit it's false so older accounts without a pin can still be edited
 * (e.g. deactivated) — the latitude field shows a warning instead.
 */
class AddressLocationFields
{
    public static function make(string $prefix = '', bool $required = true): array
    {
        $states = State::orderBy('name')->pluck('name', 'id')->toArray();
        $countries = Country::orderBy('name')->pluck('name', 'id')->toArray();
        $malaysiaId = array_search('Malaysia', $countries, true) ?: null;

        return [
            Grid::make(3)->schema([
                TextInput::make($prefix . 'unit_no')->label('Unit No')->placeholder('e.g. H-9-2'),
                TextInput::make($prefix . 'floor')->label('Floor')->placeholder('e.g. Level 1'),
                TextInput::make($prefix . 'block')->label('Block')->placeholder('e.g. Block A'),
            ]),
            Grid::make(2)->schema([
                TextInput::make($prefix . 'address_line_1')
                    ->label('Address Line 1')
                    ->placeholder('e.g. No. 123, Jalan PP22')
                    ->required($required),
                TextInput::make($prefix . 'address_line_2')
                    ->label('Address Line 2 (if any)')
                    ->placeholder('e.g. Taman Equine'),
                TextInput::make($prefix . 'postcode')
                    ->label('Postcode')
                    ->placeholder('e.g. 43300')
                    ->required($required),
                TextInput::make($prefix . 'city')
                    ->label('City')
                    ->placeholder('e.g. Seri Kembangan')
                    ->required($required),
                Select::make($prefix . 'state_id')
                    ->label('State')
                    ->options($states)
                    ->searchable()
                    ->placeholder('Select State')
                    ->required($required),
                Select::make($prefix . 'country_id')
                    ->label('Country')
                    ->options($countries)
                    ->searchable()
                    ->default($malaysiaId)
                    ->placeholder('Select Country')
                    ->required($required),
            ]),

            ViewField::make($prefix . 'location_picker')
                ->label('Confirm location on map')
                ->view('filament.forms.location-picker')
                ->viewData([
                    'prefix' => $prefix,
                    'apiKey' => config('services.google_maps.browser_key'),
                    'states' => $states,
                ])
                ->dehydrated(false)
                ->columnSpanFull(),

            Grid::make(2)->schema([
                TextInput::make($prefix . 'latitude')
                    ->label('Latitude')
                    ->numeric()
                    ->required($required)
                    ->helperText(fn ($state) => blank($state)
                        ? '⚠ No pin yet — this account will NOT be auto-assigned any jobs until a location is set.'
                        : 'Set by the map pin. Used to auto-assign the nearest jobs.'),
                TextInput::make($prefix . 'longitude')
                    ->label('Longitude')
                    ->numeric()
                    ->required($required),
            ]),
        ];
    }
}
