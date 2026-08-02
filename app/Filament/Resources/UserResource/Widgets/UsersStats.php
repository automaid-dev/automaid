<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;

class UsersStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Customer', User::role('customer')->count()),
            Stat::make('Rider', User::role('rider')->count()),
            Stat::make('Merchant', User::role('merchant')->count()),            
            Stat::make('Admin', User::role(['super_admin', 'admin'])->count()),
        ];
    }
}
