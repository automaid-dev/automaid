<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use App\Filament\Resources\CommissionResource\Widgets\CommissionStats;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\View;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form as FilamentForm;

class CommissionPage extends Page
{
    protected static string $resource = CommissionResource::class;

    protected static ?string $title = 'Commissions';

    protected static string $view = 'filament.resources.commission-resource.pages.commission-page';

    /**
     * [getBreadcrumbs description]
     * @return [type] [description]
     */
    public function getBreadcrumbs(): array
    {
        return [
            $this->getUrl() => 'Commissions',
            "" => 'List',
        ];
    }

    /**
     * [mount description]
     * @return [type] [description]
     */
    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * [getForm description]
     * @param  string $name [description]
     * @return [type]       [description]
     */
    public function getForm(string $name): ?FilamentForm
    {
        return $this->makeForm()
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    /**
     * [getFormSchema description]
     * @return [type] [description]
     */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make()
                ->contained(false)
                ->tabs([
                    Tabs\Tab::make('Commission Paid')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            View::make('filament.resources.commission.commission-paid'),
                        ]),
                    Tabs\Tab::make('Pending Payout')
                        ->icon('heroicon-o-clock')
                        ->label('Pending Payout')
                        ->schema([
                            View::make('filament.resources.commission.pending-payout'),
                        ]),
                ])
        ];
    }
}