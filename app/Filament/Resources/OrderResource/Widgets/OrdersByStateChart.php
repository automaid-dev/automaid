<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;

class OrdersByStateChart extends ChartWidget
{
    protected static ?string $heading = 'Total orders by state';

    /**
     * [getData description]
     * @return [type] [description]
     */
    protected function getData(): array
    {
        // billing_state stores a numeric state_id (see how it's set in
        // BookingController::schedule — `$order->billing_state =
        // $pickup->state_id`), not a readable name like billing_city
        // does, so this needs an actual join to show state names
        // rather than raw numbers as chart labels.
        $rows = Order::whereNotNull('orders.booking_id')
            ->leftJoin('states', 'orders.billing_state', '=', 'states.id')
            ->selectRaw('COALESCE(states.name, \'Unspecified\') as state, COUNT(*) as total')
            ->groupBy('state')
            ->orderByDesc('total')
            ->limit(15)
            ->pluck('total', 'state');

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $rows->values()->all(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $rows->keys()->all(),
        ];
    }

    /**
     * [getType description]
     * @return [type] [description]
     */
    protected function getType(): string
    {
        return 'bar';
    }
}
