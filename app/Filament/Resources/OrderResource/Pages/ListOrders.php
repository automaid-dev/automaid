<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Widgets\OrderStatusStats;
use App\Filament\Resources\OrderResource\Widgets\OrdersByCityChart;
use App\Filament\Resources\OrderResource\Widgets\OrdersByStateChart;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * [getHeaderWidgets description]
     * @return [type] [description]
     */
    protected function getHeaderWidgets(): array
    {
        return [
            OrderStatusStats::class,
            OrdersByCityChart::class,
            OrdersByStateChart::class,
        ];
    }

    /**
     * [getTabs description]
     * @return [type] [description]
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-m-queue-list'),
                // No modifyQueryUsing — deliberately unfiltered, so
                // every order is reachable somewhere no matter what
                // combination of flags/status it ends up in. The other
                // four tabs below are opinionated views for specific
                // workflows; this one is the safety net against an
                // order ever going "missing" between them again.

            'active' => Tab::make('Active Orders')
                ->icon('heroicon-m-calendar-date-range')
                ->modifyQueryUsing(function ($query) {
                    // "In progress": not cancelled, not already fully
                    // delivered, and not currently sitting in Pending
                    // Assign (that has its own tab). This is judged by
                    // actual delivery progress, not payment status —
                    // status=paid just means payment cleared, it says
                    // nothing about whether the bag has been delivered
                    // yet, so an order can legitimately be both "Paid"
                    // and "Active" at the same time.
                    return $query
                        ->where('status', '!=', \App\Models\Order::CANCELLED)
                        ->where('is_pending_assign', 0)
                        ->whereDoesntHave('order_statuses', function ($q) {
                            $q->where('code', \App\Models\OrderStatus::CUSTOMER_ORDER_DELIVERED)
                                ->where('is_done', true);
                        });
                }),
            'pending' => Tab::make('Pending Assign')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(function ($query) {
                    return $query
                        ->where('is_pending_assign', 1)
                        ->where('status', '!=', \App\Models\Order::CANCELLED)
                        // `is_pending_assign` is only ever cleared back
                        // to false by admin re-saving the order's rider/
                        // merchant assignment (EditOrder.php) — an order
                        // that got unstuck some other way (e.g. the
                        // auto-assign queue eventually succeeded on a
                        // later tick) can keep this flag stuck at true
                        // indefinitely even after it's fully delivered.
                        // Excluding orders that already reached customer
                        // status 05 (delivered) here is a direct,
                        // unconditional guard against that — this tab
                        // should never show a completed order regardless
                        // of what state the flag itself is in.
                        ->whereDoesntHave('order_statuses', function ($q) {
                            $q->where('code', \App\Models\OrderStatus::CUSTOMER_ORDER_DELIVERED)
                                ->where('is_done', true);
                        });
                }),
            'paid' => Tab::make('Paid')
                ->icon('heroicon-m-banknotes')
                ->modifyQueryUsing(function ($query) {
                    return $query->where('status', \App\Models\Order::PAID);
                }),
            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(function ($query) {
                    return $query->where('status', \App\Models\Order::CANCELLED);
                }),

            ];
    }


}
