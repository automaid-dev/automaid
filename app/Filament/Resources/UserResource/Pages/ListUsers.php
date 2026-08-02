<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\UsersStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\UserResource\Widgets\UserTabbedWidget;
use App\Filament\Resources\UserResource\Widgets\UsersWidget;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    // protected static ?string $title = 'Create New User';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];

    }

    /**
     * [getTabs description]
     * @return [type] [description]
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users')
                ->icon('heroicon-m-users'),
            'pending' => Tab::make('Pending Approval')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query
                        ->where('status', User::ONBOARDING)
                        ->whereDoesntHave('roles', function ($q) {
                            $q->whereIn('name', ['customer']);
                        })
                ),
        ];
    }

    /**
     * [getDefaultActiveTab description]
     * @return [type] [description]
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    /**
     * [getHeaderWidgets description]
     * @return [type] [description]
     */
    protected function getHeaderWidgets(): array {
        return [
            // UserTabbedWidget::class,            
            
            // UsersWidget::class,


        ];
    }




}
