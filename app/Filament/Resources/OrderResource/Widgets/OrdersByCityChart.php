<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;

class OrdersByCityChart extends ChartWidget
{
    protected static ?string $heading = 'Total orders by city';

    /**
     * [getData description]
     * @return [type] [description]
     */
    protected function getData(): array
    {
        // Same booking_id-not-null scope OrderResource's own table
        // uses. Empty/null city grouped under "Unspecified" rather
        // than silently dropped, so the totals here still add up to
        // the full order count.
        $rows = Order::whereNotNull('booking_id')
            ->selectRaw('COALESCE(NULLIF(billing_city, \'\'), \'Unspecified\') as city, COUNT(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(15)
            ->pluck('total', 'city');

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $rows->values()->all(),
                    'backgroundColor' => '#3b82f6',
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
