<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Resources\SupportTicketResource\RelationManagers;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

class SupportTicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    /**
     * [canCreate description]
     * @return [type] [description]
     */
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No') 
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->label('Ticket No') 
                    ->sortable(), 
                TextColumn::make('order.id')
                    ->label('Order ID')
                    ->getStateUsing(function ($record) {
                        return $record->order
                            ? $record->order->id
                            : '-';
                    }) 
                    ->sortable(), 
                TextColumn::make('user.name')
                    ->label('Customer Name')
                    ->sortable(),
                TextColumn::make('issue_type')
                    ->label('Issue Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'open' => 'info',
                        'closed' => 'success',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open' => 'OPEN',
                        'closed' => 'CLOSED',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Ticket::OPEN => 'Open',
                        Ticket::CLOSED => 'Closed',
                    ])
                    ->placeholder('All Status')
                    ->label(false),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);

    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Support Tickets';
    }
}
