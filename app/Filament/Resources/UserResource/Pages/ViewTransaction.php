<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Transaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        $labels = [
            Transaction::PURCHASE_BAG => 'BAG PURCHASE',
            Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
            Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
            Transaction::BOOKING => 'BOOKING',
        ];
        $statuses = [
            'pending' => ['label' => 'Pending', 'color' => 'bg-gray-200 text-gray-800'],
            'paid' => ['label' => 'Paid', 'color' => 'bg-green-200 text-green-800'],
            'failed' => ['label' => 'Failed', 'color' => 'bg-red-200 text-red-800'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-yellow-200 text-yellow-800'],
        ];

        return $form->schema([
            Grid::make(3)
                ->schema([

                    // Left Column
                    Grid::make()
                        ->schema([

                            Section::make('Customer Details') 
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_name')->label(false)->content('Name'),
                                            Placeholder::make('name')->label(false)->content(fn ($record) => $record->order?->user?->name ?? '-'),
                                            Placeholder::make('label_email')->label(false)->content('Email'),
                                            Placeholder::make('email')->label(false)->content(fn ($record) => $record->order?->user?->email ?? '-'),
                                            Placeholder::make('label_phone')->label(false)->content('Phone'),
                                            Placeholder::make('phone')->label(false)->content(fn ($record) => $record->order?->user?->mobile_no ?? '-'),
                                            Placeholder::make('label_name')->label(false)->content('Subscription'),
                                            Placeholder::make('subscription')->label(false)->content(fn ($record) => $record->order?->user?->subscribe ? 'YES' : 'NO' ),
                                        ]),
                                ]),

                            Section::make('Payment Details') 
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_payment_type')->label(false)->content('Payment Type'),
                                            Placeholder::make('payment_type')->label(false)->content(fn ($record) => $record->payment->payment_method ?? '-'),
                                            Placeholder::make('label_transaction_id')->label(false)->content('Transaction ID'),
                                            Placeholder::make('transaction_id')->label(false)->content(fn ($record) => $record->id ?? '-'),
                                            Placeholder::make('label_date_time')->label(false)->content('Date & Time'),
                                            Placeholder::make('date_time')->label(false)->content(fn ($record) => $record->created_at ?? '-'),
                                        ]),
                                ])

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([

                            Section::make('Order Details') // First section
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_order_type')->label(false)->content('Type'),
                                            Placeholder::make('order_type')->label(false)->content(fn ($record) => $labels[$record->order->order_type ?? null] ?? '-'),
                                            Placeholder::make('label_amount')->label(false)->content('Total Amount (RM)'),
                                            Placeholder::make('grand_total')->label(false)->content(fn ($record) => $record->order->grand_total ?? '-'),
                                            Placeholder::make('label_status')->label(false)->content('Status'),
                                            Placeholder::make('status')->label(false)->content(fn ($record) => $record->order->status ?? '-'),
                                        ]),
                                ])                             

                        ])->columnSpan(1),
                ]),
        ]);
    }
}
