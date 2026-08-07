<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BagResource\Pages;
use App\Filament\Resources\BagResource\RelationManagers;
use App\Models\Bag;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class BagResource extends Resource
{
    protected static ?string $model = Bag::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 5;

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

    /**
     * [getEloquentQuery description]
     * @return [type] [description]
     */
    public static function getEloquentQuery(): Builder
    {
        // Previously this only showed bags whose order was already
        // Order::PAID and whose own status_payment was Bag::PAID —
        // meaning a purchase stuck pending (payment gateway callback
        // never arrived, or still awaiting payment) was invisible here
        // entirely, with no way for an admin to even find it to
        // investigate. The Status column below already badges
        // processing/delivered/cancelled — showing every purchase_bag
        // order here (regardless of payment status) lets that column do
        // its job instead of the query silently hiding anything that
        // hasn't succeeded yet.
        return parent::getEloquentQuery()
            ->whereHas('order', function ($q) {
                $q->where('order_type', Order::PURCHASE_BAG); // order_type purchase_bag only
            });
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.transaction.id')
                    ->label('Transaction ID')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return $state ?: '-';
                    }),
                TextColumn::make('delivery_to')
                    ->label('Delivery To')
                    ->formatStateUsing(fn ($record) => "{$record->order->delivery_address_line_1}" .' ' . "{$record->order->delivery_address_line_2}" . "<br>" . "{$record->order->delivery_city}" . " " . "{$record->order->delivery_postcode}" . " " . "{$record->order->delivery_state}" . " " . "{$record->order->delivery_country}")
                    ->html()
                    ->default('-')
                    ->sortable(),
                TextColumn::make('order.quantity')
                    ->label('Quantity')
                    ->sortable(),
                TextColumn::make('order.created_at')
                    ->label('Order Date')
                    ->formatStateUsing(fn ($state) => $state->format('d M Y'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'processing' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->sortable(),
                TextColumn::make('status_payment')
                    ->label('Payment Status')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => strtoupper($state ?? 'unknown'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')            
            ->filters([
                Tables\Filters\SelectFilter::make('status_payment')
                    ->label('Payment Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Fulfillment Status')
                    ->options([
                        'processing' => 'Processing',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ]);
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
            'index' => Pages\ListBags::route('/'),
            'create' => Pages\CreateBag::route('/create'),
            'edit' => Pages\EditBag::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Bag Purchased';
    }
}
