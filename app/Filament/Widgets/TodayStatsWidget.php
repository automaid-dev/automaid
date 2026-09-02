<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use App\Models\Order;

class TodayStatsWidget extends BaseWidget
{
    /**
     * Same $types list as StatsWidget::getTotalSales() — sales include
     * subscription/bag-purchase/booking orders, not just bookings.
     * @return array
     */
    protected function salesTypes(): array
    {
        return [
            Order::SUBSCRIPTION,
            Order::PURCHASE_BAG,
            Order::BOOKING,
        ];
    }

    /**
     * Count of today's paid booking orders — matches the existing
     * "Total orders" stat's own convention (booking-type only), just
     * scoped to today specifically rather than whatever date range is
     * selected elsewhere on the dashboard.
     * @return int
     */
    public function getTodayOrders(): int
    {
        return Order::where(['order_type' => Order::BOOKING])
            ->whereDate('created_at', now()->toDateString())
            ->paid()
            ->count();
    }

    /**
     * Total paid sales value across all order types, today only.
     * @return float
     */
    public function getTodaySales(): float
    {
        return (float) Order::whereIn('order_type', $this->salesTypes())
            ->where(['status' => Order::PAID])
            ->whereDate('created_at', now()->toDateString())
            ->sum('grand_total');
    }

    /**
     * Total paid sales value across all order types, from the start of
     * the current calendar month through now.
     * @return float
     */
    public function getMonthlySales(): float
    {
        return (float) Order::whereIn('order_type', $this->salesTypes())
            ->where(['status' => Order::PAID])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('grand_total');
    }

    /**
     * [getStats description]
     * @return [type] [description]
     */
    protected function getStats(): array
    {
        return [
            Card::make("Today's orders", number_format($this->getTodayOrders())),
            Card::make("Today's sales", 'RM ' . number_format($this->getTodaySales(), 2)),
            Card::make('Monthly sales', 'RM ' . number_format($this->getMonthlySales(), 2)),
        ];
    }
}
