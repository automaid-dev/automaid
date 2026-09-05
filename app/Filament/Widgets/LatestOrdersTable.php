<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Order;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LatestOrdersTable extends BaseWidget
{
    protected static ?string $heading = 'Latest Orders';

    protected int|string|array $columnSpan = [
        'default' => 2,
    ];

    public ?string $startDate = null;
    public ?string $endDate = null;

    // Same event name/payload DateFilterWidget already dispatches for
    // every other dashboard widget (StatsWidget, OrdersChart,
    // TotalSalesChart) — this table just never listened for it before,
    // so it always showed the latest 10 orders overall regardless of
    // whatever range was selected elsewhere on the same dashboard.
    protected $listeners = ['updateStats' => 'updateDateRange'];

    /**
     * [mount description]
     * @return [type] [description]
     */
    public function mount(): void
    {
        $this->startDate = now()->subDays(30)->toDateString();
        $this->endDate = now()->toDateString();
    }

    /**
     * [updateDateRange description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function updateDateRange($data): void
    {
        $this->startDate = $data['startDate'];
        $this->endDate = $data['endDate'];
    }

    /**
     * Same filtered query the table itself uses, minus the limit(10)
     * — shared so the export button pulls every matching order for
     * the selected range, not just the handful currently visible in
     * this "latest" preview table.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filteredQuery()
    {
        return Order::where('order_type', Order::BOOKING)
            ->where('status', Order::PAID)
            ->with(['commission_transactions.commission', 'rider.accepted_user', 'merchant.accepted_user', 'user'])
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->latest();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->filteredQuery()->limit(10))
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportCsv()),
            ])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d M Y'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(), 
                TextColumn::make('customer_latest_status.status.desc')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Payment Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('Amount (RM)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('rider_commission')
                    ->label('Commission Rider (RM)')
                    ->getStateUsing(fn ($record) => number_format($this->riderCommission($record), 2))
                    ->toggleable(),
                TextColumn::make('merchant_commission')
                    ->label('Commission Merchant (RM)')
                    ->getStateUsing(fn ($record) => number_format($this->merchantCommission($record), 2))
                    ->toggleable(),
                TextColumn::make('net_amount')
                    ->label('Total After Commission (RM)')
                    ->getStateUsing(function ($record) {
                        $net = ($record->grand_total ?? 0) - $this->riderCommission($record) - $this->merchantCommission($record);
                        return number_format($net, 2);
                    })
                    ->toggleable(),
            ]);
    }

    /**
     * Streams a CSV of every order matching the currently-selected
     * date range (not just the 10 shown in the preview table above) —
     * plain CSV rather than a real .xlsx, since that needs no new
     * composer dependency (this project doesn't have
     * maatwebsite/excel or similar installed) and opens directly in
     * Excel just as well.
     * @return StreamedResponse
     */
    protected function exportCsv(): StreamedResponse
    {
        $orders = $this->filteredQuery()->get();

        $filename = 'orders_' . ($this->startDate ?? 'all') . '_to_' . ($this->endDate ?? 'now') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order Date', 'Order ID', 'Customer', 'Status', 'Payment Status', 'Amount (RM)',
                'Commission Rider (RM)', 'Commission Merchant (RM)', 'Total After Commission (RM)',
            ]);
            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->created_at?->format('d M Y'),
                    $order->id,
                    $order->user->name ?? '-',
                    $order->customer_latest_status?->status?->desc ?? '-',
                    ucfirst($order->status ?? '-'),
                    number_format($order->grand_total ?? 0, 2),
                    number_format($this->riderCommission($order), 2),
                    number_format($this->merchantCommission($order), 2),
                    number_format(($order->grand_total ?? 0) - $this->riderCommission($order) - $this->merchantCommission($order), 2),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * This order's rider's own commission — matched by comparing each
     * commission_transaction's own Commission (wallet) owner against
     * the specific user who was accepted as this order's rider, since
     * a single order's commission_transactions cover BOTH the rider's
     * and merchant's cut together, not just one.
     * @param  Order $record [description]
     * @return float
     */
    protected function riderCommission($record): float
    {
        $riderUserId = $record->rider?->accepted_user?->id;
        if (!$riderUserId) {
            return 0;
        }
        return (float) $record->commission_transactions
            ->filter(fn ($t) => $t->commission?->user_id === $riderUserId)
            ->sum('final_amount');
    }

    /**
     * Same matching as riderCommission() above, against the merchant
     * accepted for this order instead.
     * @param  Order $record [description]
     * @return float
     */
    protected function merchantCommission($record): float
    {
        $merchantUserId = $record->merchant?->accepted_user?->id;
        if (!$merchantUserId) {
            return 0;
        }
        return (float) $record->commission_transactions
            ->filter(fn ($t) => $t->commission?->user_id === $merchantUserId)
            ->sum('final_amount');
    }
}
