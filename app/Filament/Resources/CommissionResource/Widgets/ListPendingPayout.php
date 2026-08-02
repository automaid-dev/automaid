<?php

namespace App\Filament\Resources\CommissionResource\Widgets;

use App\Filament\Resources\CommissionResource;
use App\Models\Commission;
use App\Models\CommissionTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class ListPendingPayout extends BaseWidget
{
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
                    ->where('status', Commission::PENDING)
                    ->with('user.roles', 'user.rider', 'user.merchant')
                    ->withCount(['transactions as total_order'])
                    ->withSum(['transactions as total_commission' => fn($q) => $q->whereNotNull('final_amount')], 'final_amount')
                    ->latest()
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('user.roles.display_name')
                    ->badge(),
                TextColumn::make('bank_no')
                    ->label('Bank Acc. No.')
                    ->getStateUsing(fn ($record) => true)
                    ->formatStateUsing(function ($state, $record) {
                        $user = $record->user;
                        $bankDetails = $this->getBankDetailsByUser($user);
                        return $bankDetails['no'] ?? '-';
                    }),
                TextColumn::make('bank_name')
                    ->label('Bank Name')
                    ->getStateUsing(fn ($record) => true)
                    ->formatStateUsing(function ($state, $record) {
                        $user = $record->user;
                        $bankDetails = $this->getBankDetailsByUser($user);
                        return $bankDetails['name'] ?? '-';
                    }),
                TextColumn::make('total_order') // was total_orders
                    ->label('Total Orders'),
                TextColumn::make('total_commission') // was amount
                    ->label('Amount (RM)'),
            ])
            ->recordUrl(fn ($record) => url("/commissions/{$record->id}/view"))
            ->actions([
                Action::make('edit')
                    ->label(false)
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => url("/commissions/{$record->id}/view"))
                    ->color('primary'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        '1' => 'RIDER',
                        '2' => 'MERCHANT',
                    ])
                    ->placeholder('All Roles')
                    ->label(false),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('date')->label(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date'], fn ($q) => $q->whereDate('created_at', $data['date']));
                    })
                    ->label(''),
            ], layout: FiltersLayout::AboveContent)
            ->bulkActions([
                Tables\Actions\BulkAction::make('set_paid')
                    ->label('Set status as paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmation')
                    ->modalDescription("Are you sure you want to mark the status as 'Paid'? Note that this is a manual approval, and payment must be made directly to the user")
                    ->modalWidth('lg')
                    ->action(function (Collection $records) {
                        $updatedCount = 0;
                        foreach ($records as $record) {
                            if ($record->status !== Commission::PAID) {
                                $record->update(['status' => Commission::PAID]);
                                $record->transactions()->update(['status' => CommissionTransaction::PAID]);
                                $updatedCount++;
                            }
                        }
                        Notification::make()
                            ->title("Set $updatedCount record(s) as paid.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('generate_payout')
                    ->label('Generate payout')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmation')
                    ->modalDescription('Are you sure you want to disburse this payout?')
                    ->modalWidth('lg')

                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            // your logic for generating payout
                        }
                    }),
            ])
            // ->recordUrl(CommissionResource::getUrl('pending'))
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

    /**
     * [getBankDetailsByUser description]
     * @param  [type] $user [description]
     * @return [type]       [description]
     */
    private function getBankDetailsByUser($user): array
    {
        if (!$user) {
            return ['no' => '-', 'name' => '-'];
        }
        $roleName = $user->roles->first()?->name;
        return match ($roleName) {
            'rider' => [
                'no' => $user->rider?->bank_no,
                'name' => $user->rider?->bank_name,
            ],
            'merchant' => [
                'no' => $user->merchant?->bank_no,
                'name' => $user->merchant?->bank_name,
            ],
            default => ['no' => '-', 'name' => '-'],
        };
    }
}