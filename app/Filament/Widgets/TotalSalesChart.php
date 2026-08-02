<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;
use Carbon\Carbon;

class TotalSalesChart extends ChartWidget
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
        return 'Total sales (' . $start->format('j F') . ' - ' . $end->format('j F Y') . ')';
    }

    /**
     * [getData description]
     * @return [type] [description]
     */
    protected function getData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate) : now()->subDays(30);
        $end = $this->endDate ? Carbon::parse($this->endDate) : now();

        $sales = Order::selectRaw('MONTH(created_at) as month, SUM(grand_total) as total')
            ->where(['status' => Order::PAID])
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            // ->whereYear('created_at', now()->year) 
            ->groupBy('month')
            ->pluck('total', 'month'); 

        $monthlyTotals = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyTotals[] = $sales[$i] ?? 0; 
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $monthlyTotals,
                    'borderColor' => '#3B82F6',
                    'pointBackgroundColor' => '#3B82F6',
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
