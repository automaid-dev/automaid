<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
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

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    /**
     * [getHeaderActions description]
     * @return [type] [description]
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Fatimah Zahra binti Ismail (Order)';
        // return $this->record->user->name . ' (' . $this->record->type . ')';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return 'Fatimah Zahra binti Ismail (Order)';
        // return $this->record->user->name . ' (' . $this->record->type . ')';        
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
                            Section::make('Customer details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('customer_name_label')->label(false)->content('Name'),
                                            Placeholder::make('customer_name')
                                                ->label(false)
                                                ->content('Fatimah Zahra binti Ismail')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('email_label')->label(false)->content('Email'),
                                            Placeholder::make('email')
                                                ->label(false)
                                                ->content('fatimah_zahra@gmail.com')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('phone_label')->label(false)->content('Phone'),
                                            Placeholder::make('phone')
                                                ->label(false)
                                                ->content('019 7899 4566')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('subscription_label')->label(false)->content('Subscription'),
                                            Placeholder::make('Subscription')
                                                ->label(false)
                                                ->content('Auto Maid')
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),
                            Section::make('Payment details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('payment_type_label')->label(false)->content('Payment Type'),
                                            Placeholder::make('payment_type')
                                                ->label(false)
                                                ->content('Gkash')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('transaction_ID_label')->label(false)->content('Transaction ID'),
                                            Placeholder::make('transaction_ID')
                                                ->label(false)
                                                ->content('tx0000001')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('date_time_label')->label(false)->content('Date & Time'),
                                            Placeholder::make('date_time')
                                                ->label(false)
                                                ->content('12 Nov 2024, 10:00 AM')
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),
                        ])->columnSpan(2),
                
                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make('Order details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('order_type_label')->label(false)->content('Type'),
                                            Placeholder::make('order_type')
                                                ->label(false)
                                                ->content('Order')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('total_amount_label')->label(false)->content('Total Amount (RM)'),
                                            Placeholder::make('total_amount')
                                                ->label(false)
                                                ->content('21.60')
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('status_label')->label(false)->content('Status'),
                                            Placeholder::make('status')
                                                ->label(false)
                                                ->content('Paid')
                                                ->extraAttributes(['class' => 'text-right']),
                                            
                                        ]),
                                ]),
                    ])->columnSpan(1),
                ]),

        ]);
    }
}
