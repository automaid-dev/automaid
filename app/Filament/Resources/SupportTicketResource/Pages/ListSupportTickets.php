<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return 'Support Tickets'; // Custom title
    }

    /**
     * [getTabs description]
     * @return [type] [description]
     */
    public function getTabs(): array
    {
        return [
            'customer' => Tab::make('Customer')
                ->icon('heroicon-m-users')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_type', 'customer')),                
            'rider' => Tab::make('Rider')
                ->icon('heroicon-m-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_type', 'rider')),
            'merchant' => Tab::make('Merchant')
                ->icon('heroicon-m-building-storefront')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_type', 'merchant')),
        ];
    }

}
