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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('booking_id')
                ->with([
                    'booking',
                    'rider.accepted_user',
                    'merchant.accepted_user',
                    'commission_transactions.commission',
                ]))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->formatStateUsing(fn ($record) => "Date: "."{$record->booking->pickup_date}".'<br>Order #: <strong>'."{$record->id}".'</strong><br>ID: '."{$record->series_no}")
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
                        default => 'gray',
                    })
                    ->sortable(),  
                TextColumn::make('billing_city')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('billing_state')
                    ->label('State')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_latest_status.status.desc')
                    ->label('Status Order')
                    ->badge()
                    ->sortable(),

                // ---- Per-order money breakdown ----
                // Washing/add-on/delivery come from `booking` (the
                // operational record — also what commission is
                // calculated from); insurance/voucher/SST only ever
                // existed on `order` (bookings has no columns for
                // them). Toggleable + hidden by default since 8 extra
                // columns would otherwise make the list unreadably
                // wide — admin can switch them on via the column
                // toggle button.
                TextColumn::make('booking.washing_charge')
                    ->label('Washing (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('booking.addon_charge')
                    ->label('Add-on (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('insurance_fee')
                    ->label('Insurance (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount')
                    ->label('Voucher (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_total')
                    ->label('SST (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('booking.delivery_charge')
                    ->label('Delivery (RM)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rider_commission_total')
                    ->label('Rider Commission (RM)')
                    ->state(function ($record) {
                        $riderUserId = $record->rider->accepted_user->id ?? null;
                        if (!$riderUserId) return null;
                        return $record->commission_transactions
                            ->where('commission.user_id', $riderUserId)
                            ->sum('final_amount');
                    })
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('merchant_commission_total')
                    ->label('Merchant Commission (RM)')
                    ->state(function ($record) {
                        $merchantUserId = $record->merchant->accepted_user->id ?? null;
                        if (!$merchantUserId) return null;
                        return $record->commission_transactions
                            ->where('commission.user_id', $merchantUserId)
                            ->sum('final_amount');
                    })
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),
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
