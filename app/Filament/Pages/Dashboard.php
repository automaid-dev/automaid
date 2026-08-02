<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\DateFilterWidget;
use App\Filament\Widgets\LatestOrdersTable;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\StatsWidget;
use App\Filament\Widgets\TotalSalesChart;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected function getHeaderWidgets(): array
    {
        return [
            DateFilterWidget::class,
            StatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            OrdersChart::class,
            TotalSalesChart::class,
            LatestOrdersTable::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}
