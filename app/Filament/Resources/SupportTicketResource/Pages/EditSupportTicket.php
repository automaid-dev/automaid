<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Mail\ReplySupportTicketEmail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\OneSignalService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected static ?string $model = Ticket::class;

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
        
        // return [
        //     Action::make('cancel')
        //         ->label('Cancel')
        //         ->url($this->getResource()::getUrl()) // go back to list page
        //         ->color('gray'),

        //     Action::make('save')
        //         ->label('Save Changes')
        //         ->submit('save') // triggers form save
        //         ->color('primary'),
        // ];
    }

    /**
     * [afterSave description]
     * @return [type] [description]
     */
    protected function afterSave(): void
    {
        $this->redirect($this->getResource()::getUrl());
    }    

    /**
     * [getListeners description]
     * @return [type] [description]
     */
    protected function getListeners(): array
    {
        return [
            'ticketReplyAdded' => '$refresh', // re-render parent
        ];
    }

    public array $merchantResponses = [];

    /**
     * [mount description]
     * @param  int    $record [description]
     * @return [type]         [description]
     */
    public function mount(string|int $record): void
    {
        parent::mount($record);
    }

    /**
     * [loadReplies description]
     * @return [type] [description]
     */
    public function loadReplies(): void
    {
        $this->merchantResponses = TicketReply::where('ticket_id', $this->record->id)
            ->latest()
            ->get()
            ->map(fn ($reply) => [
                'title' => $reply->title,
                'description' => $reply->description,
                'date' => $reply->created_at->format('d M Y \a\t h:i A'),
            ])
            ->toArray();
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        $this->loadReplies();
        return $form->schema([
            Grid::make(3)
                ->schema([

                    // Left Column
                    Grid::make()
                        ->schema([
                            Section::make('Issue Details')
                                ->schema([
                                    Placeholder::make('issue')->disableLabel(true)
                                        ->content(fn ($record) => $record?->issue ?? '-'),
                                    View::make('images/support-ticket')
                                        ->viewData([
                                            'record' => $this->record,
                                            'hasImage' => !empty(optional($this->record)->image),
                                        ]),
                                ]),

                            Section::make('Update to Customer')
                                ->schema([
                                    Placeholder::make('header')
                                        ->disableLabel()
                                        ->content('History'),

                                    Grid::make(12)
                                        ->schema(
                                            empty($this->merchantResponses)
                                                ? [
                                                    Placeholder::make('empty')
                                                        ->disableLabel()
                                                        ->content('No reply found.')
                                                        ->columnSpanFull(),
                                                ]
                                                : array_map(function ($response) {
                                                    return Card::make([
                                                        Placeholder::make('title_' . Str::random(5))
                                                            ->disableLabel()
                                                            ->content($response['title']),
                                                        Placeholder::make('desc_' . Str::random(5))
                                                            ->disableLabel()
                                                            ->content(fn () => new \Illuminate\Support\HtmlString($response['description'])),
                                                        Placeholder::make('date_' . Str::random(5))
                                                            ->disableLabel()
                                                            ->content($response['date']),
                                                    ])
                                                    ->extraAttributes(['class' => 'border border-gray-100 p-1 shadow-sm rounded-lg']);
                                                }, $this->merchantResponses)
                                        )
                                ])
                                ->headerActions([
                                    Action::make('updateCustomer')
                                        ->label('Add Reply')
                                        ->icon('heroicon-m-pencil-square')
                                        ->button()
                                        ->form([
                                            Grid::make(1)
                                                ->schema([
                                                    TextInput::make('title')
                                                        ->label('Title')
                                                        ->placeholder('e.g., Case under review')
                                                        ->required(),
                                                    RichEditor::make('description')
                                                        ->label('Description')
                                                        ->placeholder('Enter your description here')
                                                        ->toolbarButtons([
                                                            'bold',
                                                            'bulletList',
                                                            'italic',
                                                            'link',
                                                            'orderedList',
                                                        ])
                                                ]),
                                        ])
                                        ->action(function (array $data): void {

                                            // save reply ticket
                                            $data['ticket_id'] = $this->record->id; 
                                            $data['created_by'] = auth()->user()->id; 
                                            TicketReply::create($data);
                                            
                                            // send email 
                                            $user = $this->record->user;
                                            $subject = 'Auto Maid: New Response for Your Support Ticket (Ticket No: ' . $this->record->series_no . ')';
                                            $emailContent = (new ReplySupportTicketEmail($user->name, $subject, $this->record))->render();
                                            $onesignal = new OneSignalService();
                                            $onesignal->sendEmail(
                                                $user->email,
                                                $subject,
                                                $emailContent,
                                            );

                                            // send pn to customer (if ticket is posted by customer)
                                            if ($this->record->user_type == 'customer') {
                                                event(new \App\Events\CustomerNewSupportTicket($user, $this->record, 1));
                                            }

                                            // send pn to rider (if ticket is posted by rider)
                                            if ($this->record->user_type == 'rider') {
                                                event(new \App\Events\CustomerNewSupportTicket($user, $this->record, 2));
                                            }

                                            // send pn to merchant (if ticket is posted by merchant)
                                            if ($this->record->user_type == 'merchant') {
                                                event(new \App\Events\CustomerNewSupportTicket($user, $this->record, 3));
                                            }
                                        })
                                        ->after(function (): void {
                                            $this->dispatch('ticketReplyAdded'); 
                                        })
                                ])

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([

                            Section::make('Ticket Details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_ticket_no')->label(false)->content('Ticket No'),
                                            Placeholder::make('ticket_no')->label(false)->content(fn ($record) => $record->series_no ?? '-')->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('label_order_id')->label(false)->content('Order ID'),
                                            Placeholder::make('order_type')->label(false)->content(fn ($record) => $record->order?->id ?? '-')->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('label_customer')->label(false)->content('Customer'),
                                            Placeholder::make('grand_total')->label(false)->content(fn ($record) => $record->user?->name ?? '-')->extraAttributes(['class' => 'text-right']),
                                            Placeholder::make('label_issue_type')->label(false)->content('Issue Type'),
                                            Placeholder::make('status')->label(false)->content(fn ($record) => $record->issue_type ?? '-')->extraAttributes(['class' => 'text-right']),
                                        ]),
                                ]),              

                            Section::make('Update Status')
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            Select::make('status')
                                                ->label('Status')
                                                ->selectablePlaceholder(false)
                                                ->options([
                                                    'open' => 'Open',
                                                    'closed' => 'Closed',
                                                ]),
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
                                ])              

                        ])->columnSpan(1),
                ]),

        ]);
    }
}
