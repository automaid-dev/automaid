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
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    'rider_pending.user',
                    'merchant.accepted_user',
                    'merchant_pending.user.merchant',
                    'commission_transactions.commission',
                ]))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('series_no')
                    ->formatStateUsing(fn ($record) => "Date: "."{$record->booking?->pickup_date}".'<br>Order #: <strong>'."{$record->id}".'</strong><br>ID: '."{$record->series_no}")
                    ->html()
                    ->label('Orders')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('id') // must set valid column — service_category_id lives on
                    // Booking/OrderBooking only, never on Order itself (no such column
                    // exists on `orders` at all), so this can't bind directly to a
                    // real Order attribute — same reasoning as the "Assigned To"
                    // column above. booking.items presence is the reliable signal
                    // already used elsewhere (EditOrder's own "Dry Cleaning Items"
                    // section checks the exact same thing).
                    ->formatStateUsing(fn ($record) => !empty($record->booking?->items) ? 'Dry Cleaning' : 'Wash & Fold')
                    ->label('Order Type')
                    ->badge()
                    ->color(fn ($record) => !empty($record->booking?->items) ? 'info' : 'gray'),
                TextColumn::make('id') // must set valid column
                    ->formatStateUsing(function ($record) {
                        // rider/merchant (Order::rider()/merchant()) only match
                        // is_accepted=true jobs — a job admin just manually
                        // assigned starts as pending, same as the auto-assign
                        // flow, so those relations are correctly null until the
                        // rider/merchant actually taps Accept. Previously this
                        // showed a bare "-" for a pending assignment, which
                        // looked exactly like "nothing was assigned at all" —
                        // falling back to rider_pending/merchant_pending (also
                        // AssignJob, matching on code alone rather than
                        // is_accepted too) shows who it's pending on instead.
                        $riderName = $record->rider?->accepted_user?->name
                            ?? ($record->rider_pending?->user?->name ? "{$record->rider_pending->user->name} (Pending)" : null)
                            ?? '-';
                        $merchantName = $record->merchant?->accepted_user?->merchant?->company_name
                            ?? ($record->merchant_pending?->user?->merchant?->company_name ? "{$record->merchant_pending->user->merchant->company_name} (Pending)" : null)
                            ?? '-';
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
                TextColumn::make('rider_settlement_status')
                    ->label('Rider Payout')
                    ->badge()
                    ->state(function ($record) {
                        $riderUserId = $record->rider->accepted_user->id ?? null;
                        if (!$riderUserId) return null;
                        $txn = $record->commission_transactions->where('commission.user_id', $riderUserId)->first();
                        if (!$txn) return null;
                        return $txn->status === \App\Models\CommissionTransaction::PAID ? 'Settled' : 'Pending';
                    })
                    ->color(fn (?string $state) => $state === 'Settled' ? 'success' : ($state === 'Pending' ? 'warning' : 'gray'))
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
                TextColumn::make('merchant_settlement_status')
                    ->label('Merchant Payout')
                    ->badge()
                    ->state(function ($record) {
                        $merchantUserId = $record->merchant->accepted_user->id ?? null;
                        if (!$merchantUserId) return null;
                        $txn = $record->commission_transactions->where('commission.user_id', $merchantUserId)->first();
                        if (!$txn) return null;
                        return $txn->status === \App\Models\CommissionTransaction::PAID ? 'Settled' : 'Pending';
                    })
                    ->color(fn (?string $state) => $state === 'Settled' ? 'success' : ($state === 'Pending' ? 'warning' : 'gray'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                // Filament's native queued export (ExportAction +
                // Exporter) needs the `exports` table AND a working
                // queue worker actually processing jobs — on this
                // server the export button did nothing because of
                // that. Streaming a plain CSV directly in the request,
                // the same way the dashboard's "Latest Orders" export
                // already does (LatestOrdersTable::exportCsv), needs
                // neither: it downloads immediately, synchronously.
                Action::make('export')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn ($livewire) => static::exportCsv($livewire->getFilteredSortedTableQuery()->get())),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                // Deliberately removed the row-level DeleteAction that
                // used to sit here. It had no ->action() override, so
                // it fell through to Filament's default behavior —
                // $record->delete(), a genuine soft-delete since Order
                // uses SoftDeletes — with no confirmation customization,
                // no reason tracking, and no updated_by attribution.
                // An unlabeled icon button with that behavior is a
                // trap: it's how two fully valid, already-paid orders
                // (#1350/#1351) ended up soft-deleted and invisible
                // everywhere, including crashing the Transaction detail
                // page for their linked transactions. "Cancel Order" on
                // the edit page (EditOrder::getHeaderActions) is the
                // correct, safe way to cancel an order — a real status
                // update with refund tracking, activity logging, and
                // customer notification, never an actual delete. There
                // was no legitimate use for this second, unsafe path
                // existing in parallel.
            ]);
    }

    /**
     * Streams a CSV of the given orders — whatever the currently
     * active tab/filters/search/sort on the table resolved to, so the
     * export always matches what the admin is actually looking at.
     * Same money-breakdown columns as the table itself.
     * @param  \Illuminate\Support\Collection $orders
     * @return StreamedResponse
     */
    protected static function exportCsv($orders): StreamedResponse
    {
        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order #', 'Order ID', 'Pickup Date', 'Customer', 'Status', 'Status Order',
                'City', 'State', 'Rider', 'Merchant',
                'Washing (RM)', 'Add-on (RM)', 'Insurance (RM)', 'Voucher (RM)', 'SST (RM)',
                'Delivery (RM)', 'Grand Total (RM)', 'Rider Commission (RM)', 'Rider Payout',
                'Merchant Commission (RM)', 'Merchant Payout',
            ]);
            foreach ($orders as $order) {
                $riderUserId = $order->rider->accepted_user->id ?? null;
                $merchantUserId = $order->merchant->accepted_user->id ?? null;
                $riderTxn = $riderUserId ? $order->commission_transactions->where('commission.user_id', $riderUserId)->first() : null;
                $merchantTxn = $merchantUserId ? $order->commission_transactions->where('commission.user_id', $merchantUserId)->first() : null;
                $riderCommission = $riderUserId
                    ? $order->commission_transactions->where('commission.user_id', $riderUserId)->sum('final_amount')
                    : 0;
                $merchantCommission = $merchantUserId
                    ? $order->commission_transactions->where('commission.user_id', $merchantUserId)->sum('final_amount')
                    : 0;

                fputcsv($handle, [
                    $order->id,
                    $order->series_no,
                    $order->booking->pickup_date ?? null,
                    $order->user->name ?? '-',
                    ucfirst($order->status ?? '-'),
                    $order->customer_latest_status->status->desc ?? '-',
                    $order->billing_city,
                    $order->billing_state,
                    $order->rider->accepted_user->name ?? '-',
                    $order->merchant->accepted_user->merchant->company_name ?? '-',
                    number_format($order->booking->washing_charge ?? 0, 2),
                    number_format($order->booking->addon_charge ?? 0, 2),
                    number_format($order->insurance_fee ?? 0, 2),
                    number_format($order->discount ?? 0, 2),
                    number_format($order->tax_total ?? 0, 2),
                    number_format($order->booking->delivery_charge ?? 0, 2),
                    number_format($order->grand_total ?? 0, 2),
                    number_format($riderCommission, 2),
                    $riderTxn ? ($riderTxn->status === \App\Models\CommissionTransaction::PAID ? 'Settled' : 'Pending') : '-',
                    number_format($merchantCommission, 2),
                    $merchantTxn ? ($merchantTxn->status === \App\Models\CommissionTransaction::PAID ? 'Settled' : 'Pending') : '-',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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
