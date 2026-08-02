<?php

namespace App\Filament\Resources\SettingResource\Pages\Addon;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditAddon extends EditRecord
{
    protected static string $resource = SettingResource::class;

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Folding Services';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Folding Service';        
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(3)
                ->schema([
                    // Left Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('title')
                                                ->label('Title')
                                                ->columnSpan(2)
                                                ->placeholder('e.g., Folding Services'),
                                            Textarea::make('description')
                                                ->label('Description')
                                                ->columnSpan(2)
                                                ->rows(8)
                                                ->autosize()
                                                ->placeholder('e.g., This is new Add-ons'),
                                        ]),
                                ]),
                        ])->columnSpan(2),
                
                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    TextInput::make('price')
                                        ->label('Price (RM)')
                                        ->placeholder('e.g., 20'),
                                    Select::make('status')
                                        ->label('Status')
                                        ->placeholder('Select status')
                                        ->options([
                                            '0' => 'Active',
                                            '1' => 'Inactive',
                                        ]),
                                ]),
                    ])->columnSpan(1),
                ]),

        ]);
    }
}
