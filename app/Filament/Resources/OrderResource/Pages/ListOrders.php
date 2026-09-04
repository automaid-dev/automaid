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
            'active' => Tab::make('Active Orders')
                ->icon('heroicon-m-calendar-date-range')
                ->modifyQueryUsing(function ($query) {
                    return $query
                        ->whereIn('status', ['pending', 'paid', 'cancelled'])
                        ->where('is_pending_assign', 0);
                }),
            'pending' => Tab::make('Pending Assign')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(function ($query) {
                    return $query
                        ->where('is_pending_assign', 1);
                }),

            ];
    }


}
