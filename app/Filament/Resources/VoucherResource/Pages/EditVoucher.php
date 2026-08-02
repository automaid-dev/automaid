<?php

namespace App\Filament\Resources\VoucherResource\Pages;

use App\Filament\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
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

class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->code;
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-commission-fee-tab' => 'General Settings',
            $this->record->code,
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.settings.edit', ['record' => 1]) . '?tab=-vouchers-discount-tab';
    }

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
                                            TextInput::make('code')
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
                                            '1' => 'RM',
                                            '2' => '%',
                                        ]),
                                    TextInput::make('discount_amount')
                                        ->label('Discount amount')
                                        ->numeric()
                                        ->rule('numeric')
                                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ''))
                                        ->dehydrateStateUsing(fn ($state) => number_format((float) $state, 2, '.', ''))
                                        ->placeholder('e.g., 20'),
                                    TextInput::make('usage_limit')
                                        ->label('Usage Limit')
                                        ->numeric()
                                        ->placeholder('e.g., 100'),
                                    Select::make('status')
                                        ->label('Status')
                                        ->selectablePlaceholder(false)
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
                                    ->label('Update')
                                    ->submit('save')
                                    ->color('primary'),
                            ])->columnSpanFull()->alignEnd(),
                    ])->columnSpan(1),
                ]),

        ]);
    }
}
