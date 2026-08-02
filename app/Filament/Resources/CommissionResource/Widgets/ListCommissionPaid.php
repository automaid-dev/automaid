<?php

namespace App\Filament\Resources\CommissionResource\Widgets;

use App\Models\Commission;
use App\Models\CommissionTransaction;
use App\Filament\Resources\CommissionResource;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\View\View;
use Livewire\Livewire;
use App\Models\Role;
use Filament\Tables\Actions\Action;

class ListCommissionPaid extends BaseWidget
{
    /**
     * [render description]
     * @return [type] [description]
     */
    public function render(): View
    {
        return view('filament.widgets.commission.commission-paid', [
            'thisWidget' => $this,
        ]);
    }
    
    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Commission::query()
                    ->where('status', Commission::PAID)
                    ->with('user.roles')
                    ->withCount(['transactions as total_order'])
                    ->withSum(['transactions as total_commission' => fn($q) => $q->whereNotNull('final_amount')], 'final_amount')
                    ->latest()
            )
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No'), 
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('user.roles.display_name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('total_order')
                    ->label('Total Orders'),
                TextColumn::make('total_commission')
                    ->label('Total Comm. (RM)')
                    ->money('myr'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => strtoupper($state))
                    ->label('Status'),
            ])
            ->recordUrl(fn ($record) => CommissionResource::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('View')
                    ->label(false)
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => CommissionResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(false)
                    ->placeholder('All Roles')
                    ->options(fn () => Role::pluck('display_name', 'name')->toArray()) // Fetch roles dynamically
                    ->query(function ($query, $data) {
                        if ($data['value']) {
                            $name = $data['value'];
                            $query->whereHas('user.roles', function ($q) use ($name) {
                                $q->where('name', $name);
                            });
                        }
                    }),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('date')->label(''),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date'], fn ($q) => $q->whereDate('created_at', $data['date']));
                    })
                    ->label(false),
            ], layout: FiltersLayout::AboveContent)
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

}