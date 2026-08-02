<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget\Card;

class UsersWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Card::make('Customer', '1200'),
            Card::make('Rider', '200'),
            Card::make('Merchant', '189'),
            Card::make('Admin', '3'),
        ];
    }
}
