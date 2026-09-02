<?php

namespace App\Filament\Resources\UserResource\Widgets\Edit\Customer;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use App\Models\Order;

class OrderStats extends BaseWidget
{
    public ?int $userId = null;

    /**
     * Same order_type scope as the sibling OrderList widget on this
     * same tab — booking orders only, so these numbers describe
     * exactly the same set of orders shown in the table below them.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return Order::where('user_id', $this->userId)
            ->where('order_type', Order::BOOKING);
    }

    /**
     * Count of this customer's PAID orders — "Sales"/"Average order
     * value" below are both derived from this same paid count/total,
     * so they stay consistent with each other.
     * @return int
     */
    public function getOrdersCount(): int
    {
        return $this->baseQuery()->paid()->count();
    }

    /**
     * Total value of every order this customer has ever placed,
     * regardless of payment/cancellation outcome — the raw order
     * volume generated, as distinct from "Sales" below (realized
     * revenue only). These two will diverge whenever any order wasn't
     * actually paid through to completion.
     * @return float
     */
    public function getGrossOrders(): float
    {
        return (float) $this->baseQuery()->sum('grand_total');
    }

    /**
     * Realized revenue — sum of grand_total for PAID orders only.
     * @return float
     */
    public function getSales(): float
    {
        return (float) $this->baseQuery()->paid()->sum('grand_total');
    }

    /**
     * Sales divided by paid order count — guarded against division by
     * zero for a customer with no paid orders yet.
     * @return float
     */
    public function getAverageOrderValue(): float
    {
        $count = $this->getOrdersCount();
        return $count > 0 ? $this->getSales() / $count : 0;
    }

    /**
     * When this customer last placed an order (any status) — the
     * order's own created_at, not the scheduled pickup date, since
     * this describes ordering activity rather than the booking itself.
     * @return string
     */
    public function getLastOrderDate(): string
    {
        $latest = $this->baseQuery()->latest()->first();
        return $latest ? $latest->created_at->format('d M Y') : '-';
    }

    /**
     * [getStats description]
     * @return [type] [description]
     */
    protected function getStats(): array
    {
        return [
            Card::make('Orders', number_format($this->getOrdersCount())),
            Card::make('Gross Orders', 'RM ' . number_format($this->getGrossOrders(), 2)),
            Card::make('Sales', 'RM ' . number_format($this->getSales(), 2)),
            Card::make('Average order value', 'RM ' . number_format($this->getAverageOrderValue(), 2)),
            Card::make('Last order', $this->getLastOrderDate()),
        ];
    }
}
