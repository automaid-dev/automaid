<?php

namespace App\Filament\Resources\SupportTicketResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\TicketReply as TicketReply2;
use App\Models\Ticket;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;

class TicketReply extends BaseWidget
{
    public ?int $ticketId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function() {
                return TicketReply2::latest()
                    ->where('ticket_id', $this->ticketId);
            })
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('title')
                    ->label('Title')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->html()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->date()
                    ->label('Created At')
                    ->sortable(), 
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
                        $data['ticket_id'] = $this->ticketId; 
                        $data['created_by'] = auth()->user()->id; 
                        TicketReply2::create($data);
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);

    }
}
