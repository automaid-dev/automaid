<?php

namespace App\Filament\Widgets;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;

class DateFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.date-filter-widget';

    protected int|string|array $columnSpan = [
        'default' => 2,
    ];

    public ?string $startDate = null;
    public ?string $endDate = null;

    /**
     * [emitUpdateStats description]
     * @return [type] [description]
     */
    protected function emitUpdateStats()
    {
        $this->dispatch('updateStats', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    /**
     * [mount description]
     * @return [type] [description]
     */
    public function mount(): void
    {
        $this->startDate = now()->subDays(30)->toDateString();
        $this->endDate = now()->toDateString();
    }

    /**
     * [resetToLast30Days description]
     * @return [type] [description]
     */
    public function resetToLast30Days()
    {
        $this->form->fill([
            'startDate' => now()->subDays(30)->toDateString(),
            'endDate' => now()->toDateString(),
        ]);        
        $this->emitUpdateStats();
    }

    /**
     * [getFormSchema description]
     * @return [type] [description]
     */
    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label(false)
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->emitUpdateStats()),

                    DatePicker::make('endDate')
                        ->label(false)
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->emitUpdateStats()),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }
}
