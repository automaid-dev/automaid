<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Shows every order currently marked is_pending_assign = true — i.e.
 * AssignOrderToRiderAndMerchant gave up finding a rider/merchant and a
 * human needs to assign it manually. This is the passive, always-visible
 * counterpart to the Telegram alert that fires at the moment an order
 * first becomes stuck (see AssignOrderToRiderAndMerchant::notifyAdminPendingAssign) —
 * the alert can be missed or scrolled past; this widget can't be, as
 * long as admin looks at the dashboard.
 */
class PendingAssignmentWidget extends BaseWidget
{
    protected static ?string $heading = '⚠️ Orders Needing Manual Assignment';

    // Negative sort = shows near the top of the dashboard, ahead of the
    // general stats/charts widgets — this is meant to be acted on, not
    // buried below routine reporting.
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::where('is_pending_assign', true)
                    ->where('order_type', Order::BOOKING)
                    ->whereNotIn('status', [Order::CANCELLED])
                    ->with('user')
                    ->latest()
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Order Placed')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d M Y, h:ia'))
                    ->sortable(),
                TextColumn::make('pending_since')
                    ->label('Pending For')
                    ->getStateUsing(fn ($record) => $record->updated_at?->diffForHumans(null, true) . ' ago')
                    ->color('danger')
                    ->weight('bold'),
                TextColumn::make('grand_total')
                    ->label('Amount (RM)')
                    ->money('MYR', divideBy: 1),
            ])
            ->actions([
                Action::make('assign')
                    ->label('Assign Now')
                    ->icon('heroicon-o-user-plus')
                    ->url(fn (Order $record) => route('filament.admin.resources.orders.edit', $record))
                    ->color('primary'),
            ])
            ->emptyStateHeading('Nothing pending — all orders are assigned')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('60s');
    }
}
