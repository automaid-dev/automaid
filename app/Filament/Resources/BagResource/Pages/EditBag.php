<?php

namespace App\Filament\Resources\BagResource\Pages;

use App\Filament\Resources\BagResource;
use App\Models\Bag;
use Carbon\Carbon;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditBag extends EditRecord
{
    protected static string $resource = BagResource::class;

    /**
     * [getHeaderActions description]
     * @return [type] [description]
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    /**
     * [getFormActions description]
     * @return [type] [description]
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->user->name . ' (' . $this->record->id . ')';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->user->name . ' (' . $this->record->id . ')';        
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
                            Section::make('Order details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('order_id_label')->label(false)->content('Order ID'),
                                            Placeholder::make('order_id')
                                                ->label(false)
                                                ->content($this->record->order_id)
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('customer_label')->label(false)->content('Customer'),
                                            Placeholder::make('customer')
                                                ->label(false)
                                                ->content($this->record->user->name)
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('customer_email')->label(false)->content('Email'),
                                            Placeholder::make('email')
                                                ->label(false)
                                                ->content($this->record->user->email)
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('order_date_label')->label(false)->content('Order date'),
                                            Placeholder::make('order_date')
                                                ->label(false)
                                                ->content($this->record->order->created_at->format('F j, Y'))
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('bags_quantity_label')->label(false)->content('Bag quantity'),
                                            Placeholder::make('bags_quantity')
                                                ->label(false)
                                                ->content(
                                                    $this->record->order->quantity . ' ' .
                                                    ($this->record->order->quantity > 1 ? 'Units' : 'Unit')
                                                )
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('transaction_label')->label(false)->content('Transaction Details'),
                                            Placeholder::make('transaction')
                                                ->label(false)
                                                ->content(function () {
                                                    $transaction = $this->record->order->transaction;
                                                    if (! $transaction) {
                                                        return '—'; // Handle null case
                                                    }
                                                    $url = route('filament.admin.resources.transactions.view', ['record' => $transaction->id]);
                                                    return new HtmlString('<a href="' . $url . '" class="text-primary underline">' . $transaction->id . '</a>');
                                                })
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('transaction_datetime')->label(false)->content('Date Time'),
                                            Placeholder::make('date_time')
                                                ->label(false)
                                                ->content(function () {
                                                    $transaction = $this->record->order->transaction;
                                                    if (! $transaction) {
                                                        return '—'; // Handle null case
                                                    }
                                                    return $transaction->created_at;
                                                })
                                                ->extraAttributes(['class' => 'text-right']),

                                        ]),
                                ]),
                            Section::make('Address details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('billing_address_label') ->label(false)->content('Billing Address'),
                                            Placeholder::make('billing_address')
                                                ->label(false)
                                                ->content($this->record->order->billing_address_line_1 . ' ' . $this->record->order->billing_address_line_2 . ' ' . $this->record->order->billing_postcode . ' ' . $this->record->order->billing_city . ' ' . $this->record->order->billing_state . ' ' . $this->record->order->billing_country)
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('delivery_address_label')->label(false)->content('Delivery Address (For delivery laundry bag)'),
                                            Placeholder::make('customer')
                                                ->label(false)
                                                ->content($this->record->order->delivery_address_line_1 . ' ' . $this->record->order->delivery_address_line_2 . ' ' . $this->record->order->delivery_postcode . ' ' . $this->record->order->delivery_city . ' ' . $this->record->order->delivery_state . ' ' . $this->record->order->delivery_country)
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),
                            Section::make('Order summary')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([

                                            Placeholder::make('amount_label')->label(false)
                                                // ->content('Auto Maid Bag (RM 10.00 per unit) x 3'),
                                                ->content("Auto Maid Bag (RM " . number_format($this->record->order->sub_total / $this->record->order->quantity, 2) . " per unit) x " . $this->record->order->quantity),
                                            Placeholder::make('amount')
                                                ->label(false)
                                                ->content('RM ' . number_format($this->record->order->sub_total / $this->record->order->quantity, 2))
                                                ->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('sst_label')->label(false)->content('SST (8%)'),
                                            Placeholder::make('sst')
                                                ->label(false)
                                                ->content("RM " . number_format($this->record->order->tax_total, 2))
                                                ->extraAttributes(['class' => 'text-right mb-0']),
                                            View::make('components.divider.divider')->columnSpan(2),
                                            Placeholder::make('sst_label')->label(false)->content('Total'),
                                            Placeholder::make('sst')
                                                ->label(false)
                                                ->content('RM '. number_format($this->record->order->grand_total, 2))
                                                ->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),
                        ])->columnSpan(2),
                
                    // Right Column
                    Grid::make()
                    ->schema([

                        Section::make('Update Status')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Select::make('status')
                                            ->label('Status')
                                            ->placeholder('Select Status')
                                            ->options([
                                                Bag::PROCESSING => 'Processing',
                                                Bag::DELIVERED => 'Delivered',
                                                Bag::CANCELLED => 'Cancelled',
                                            ]),
                                        Textarea::make('remarks')
                                            ->label('Remarks')
                                            ->rows(8)
                                            ->autosize()
                                            ->placeholder('e.g., Bag have been delivered to customer'),
                                    ]),

                                Actions::make([
                                    \Filament\Forms\Components\Actions\Action::make('cancel')
                                        ->label('Cancel')
                                        ->url($this->getResource()::getUrl())
                                        ->color('gray'),

                                    \Filament\Forms\Components\Actions\Action::make('save')
                                        ->label('Save Changes')
                                        ->action('save')
                                        ->color('primary'),
                                ])->alignEnd(),
                            ]),     

                        Section::make('Note to Customer')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Placeholder::make('note_title')->disableLabel(true)
                                            ->content($this->record->note_title ?? '-'),
                                        Placeholder::make('note_desc')->disableLabel(true)
                                            ->content(strip_tags($this->record->note_desc ?? '-'))
                                    ]),

                                Actions::make([
                                    Action::make('updateCustomer')
                                        ->label(fn () => ($this->record?->note_title || $this->record?->note_desc) ? 'Update Note' : 'Add Reply')
                                        ->icon('heroicon-m-pencil-square')
                                        ->button()
                                        ->form([
                                            Grid::make(1)
                                                ->schema([
                                                    TextInput::make('note_title')
                                                        ->label('Title')
                                                        ->placeholder('e.g., Case under review')
                                                        ->required(),
                                                    RichEditor::make('note_desc')
                                                        ->label('Description')
                                                        ->placeholder('Enter your description here')
                                                        ->toolbarButtons([
                                                            'bold',
                                                            'bulletList',
                                                            'italic',
                                                            'link',
                                                            'orderedList',
                                                        ]),
                                                ]),
                                        ])
                                        ->fillForm(function () {
                                            return [
                                                'note_title' => $this->record->note_title,
                                                'note_desc' => $this->record->note_desc,
                                            ];
                                        })
                                        ->action(function (array $data) {
                                            $this->record->update([
                                                'note_title' => $data['note_title'],
                                                'note_desc' => $data['note_desc'],
                                            ]);

                                            Notification::make()
                                                ->title('Customer note updated')
                                                ->success()
                                                ->send();
                                        }),
                                ])->alignEnd(),

                            ]),     

                    ])->columnSpan(1),
                ]),

        ]);
    }
}
