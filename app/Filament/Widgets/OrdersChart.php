<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;
use Carbon\Carbon;

class OrdersChart extends ChartWidget
{

    public ?string $startDate = null;
    public ?string $endDate = null;

    protected $listeners = ['updateStats'];

    /**
     * [updateStats description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function updateStats($data): void
    {
        $this->startDate = $data['startDate'];
        $this->endDate = $data['endDate'];
    }

    /**
     * [getHeading description]
     * @return [type] [description]
     */
    public function getHeading(): ?string
    {
        $start = $this->startDate ? Carbon::parse($this->startDate) : now()->subDays(30);
        $end = $this->endDate ? Carbon::parse($this->endDate) : now();
        return 'Orders (' . $start->format('j F') . ' - ' . $end->format('j F Y') . ')';
    }

    /**
     * [getData description]
     * @return [type] [description]
     */
    protected function getData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate) : now()->subDays(30);
        $end = $this->endDate ? Carbon::parse($this->endDate) : now();

        $orderCounts = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->where(['status' => Order::PAID])
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])        
            // ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->pluck('count', 'month'); 

        $monthlyCounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyCounts[] = $orderCounts[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $monthlyCounts,
                    'borderColor' => '#22C55E',
                    'pointBackgroundColor' => '#22C55E',
                    'fill' => true,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    /**
     * [getType description]
     * @return [type] [description]
     */
    protected function getType(): string
    {
        return 'line';
    }

    /**
     * [getOptions description]
     * @return [type] [description]
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'onClick' => null,
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
        ];
    }
}
