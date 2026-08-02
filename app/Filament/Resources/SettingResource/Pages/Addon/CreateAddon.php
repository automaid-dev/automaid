<?php

namespace App\Filament\Resources\SettingResource\Pages\Addon;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use App\Models\AddOn;

class CreateAddon extends CreateRecord
{
    protected static string $resource = SettingResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'New Add-ons';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-commission-fee-tab' => 'General Settings',
            'New Add-ons',
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * [handleRecordCreation description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function handleRecordCreation(array $data): AddOn
    {
        return AddOn::create($data);
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-add-ons-tab';
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
                                        ->numeric()
                                        ->placeholder('e.g., 20'),
                                    Select::make('status')
                                        ->label('Status')
                                        ->placeholder('Select status')
                                        ->default('active')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                        ]),
                                ]),

                            FormActions::make([
                                FormActions\Action::make('cancel')
                                    ->label('Back')
                                    ->extraAttributes(['x-on:click' => 'history.back()'])
                                    ->color('gray'),
                                FormActions\Action::make('submit')
                                    ->label('Create')
                                    ->submit('save')
                                    ->color('primary'),
                            ])->columnSpanFull()->alignEnd(),
                    ])->columnSpan(1),
                ]),

        ]);
    }
}
