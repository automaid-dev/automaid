<?php

namespace App\Filament\Resources\SettingResource\Pages\Voucher;

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

class EditVoucher extends EditRecord
{
    protected static string $resource = SettingResource::class;

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Free Shipping';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Free Shipping';        
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
                                            TextInput::make('voucher_code')
                                                ->label('Voucher Code')
                                                ->columnSpan(2)
                                                ->placeholder('e.g., Free Shipping'),
                                            Textarea::make('description')
                                                ->label('Description')
                                                ->columnSpan(2)
                                                ->rows(8)
                                                ->autosize()
                                                ->placeholder('e.g., Waive shipping fee'),
                                        ]),
                                ]),
                        ])->columnSpan(2),
                
                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    Select::make('discount_type')
                                        ->label('Discount Type')
                                        ->placeholder('Select discount type')
                                        ->options([
                                            '0' => 'RM',
                                            '1' => '%',
                                        ]),
                                    TextInput::make('discount_amount')
                                        ->label('Discount amount')
                                        ->placeholder('e.g., 20'),
                                    TextInput::make('usage_limit')
                                        ->label('Usage Limit')
                                        ->placeholder('e.g., 100'),
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
