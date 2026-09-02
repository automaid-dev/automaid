<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\DateFilterWidget;
use App\Filament\Widgets\LatestOrdersTable;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\StatsWidget;
use App\Filament\Widgets\TodayStatsWidget;
use App\Filament\Widgets\TotalSalesChart;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected function getHeaderWidgets(): array
    {
        return [
            // Fixed today's/monthly figures — placed first and kept
            // separate from StatsWidget below, since those numbers move
            // with whatever date range is selected via DateFilterWidget,
            // while these three are always "today" and "this month"
            // regardless of that filter.
            TodayStatsWidget::class,
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
