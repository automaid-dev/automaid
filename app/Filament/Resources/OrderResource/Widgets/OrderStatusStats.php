<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use App\Models\Order;

class OrderStatusStats extends BaseWidget
{
    /**
     * Same booking_id-not-null scope OrderResource's own table uses,
     * so these counts describe exactly the same set of rows visible in
     * the list below them.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return Order::whereNotNull('booking_id');
    }

    /**
     * [getStats description]
     * @return [type] [description]
     */
    protected function getStats(): array
    {
        return [
            Card::make('Total orders paid', number_format($this->baseQuery()->where('status', Order::PAID)->count())),
            Card::make('Total orders cancelled', number_format($this->baseQuery()->where('status', Order::CANCELLED)->count())),
        ];
    }
}
