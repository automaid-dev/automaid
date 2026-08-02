<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 2;

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
     * [table description]
     * @param  Tables\Table $table [description]
     * @return [type]              [description]
     */
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('booking_id'))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->formatStateUsing(fn ($record) => "Date: "."{$record->booking->pickup_date}".'<br>ID: '."{$record->series_no}")
                    ->html()
                    ->label('Orders')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('id') // must set valid column
                    ->formatStateUsing(function ($record) {
                        $riderName = $record->rider->accepted_user->name ?? '-';
                        $merchantName = $record->merchant->accepted_user->merchant->company_name ?? '-';
                        return "Rider: {$riderName}<br>Merchant: {$merchantName}";
                    })
                    ->html()
                    ->label('Assigned To')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'danger',
                        'cancelled' => 'danger',
                    })
                    ->sortable(),  
                TextColumn::make('customer_latest_status.status.desc')
                    ->label('Status Order')
                    ->badge()
                    ->sortable(),                    
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),

            ]);
    }

    /**
     * [getRelations description]
     * @return [type] [description]
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * [getPages description]
     * @return [type] [description]
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
