<?php

namespace App\Filament\Resources\CommissionResource\Widgets;

use App\Filament\Resources\CommissionResource;
use App\Models\Commission;
use App\Models\CommissionPayment;
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
                    // Previously filtered by Commission::where('status',
                    // Commission::PENDING) — a single static flag on the
                    // wallet as a whole. Once admin settled a user even
                    // once, that flag flipped to 'paid' and never reset,
                    // so any NEW commission they earned afterward (which
                    // creates a fresh PENDING CommissionTransaction
                    // underneath) became permanently invisible here —
                    // the wallet had genuinely pending money owed to
                    // them, but this list would never show it again.
                    // Checking the actual transactions directly means
                    // this reflects reality regardless of whatever the
                    // wallet-level status happens to say.
                    ->whereHas('transactions', fn ($q) => $q->where('status', CommissionTransaction::PENDING))
                    ->with('user.roles', 'user.rider', 'user.merchant')
                    ->withCount(['transactions as total_order' => fn ($q) => $q->where('status', CommissionTransaction::PENDING)])
                    ->withSum(['transactions as total_commission' => fn ($q) => $q->where('status', CommissionTransaction::PENDING)->whereNotNull('final_amount')], 'final_amount')
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
            // Previously these built raw URLs via url("/commissions/{id}/view"),
            // which omits this Filament panel's own path prefix
            // ('admin', configured in AdminPanelProvider). That produced
            // a link to /commissions/{id}/view instead of the real
            // /admin/commissions/{id}/view, 404ing every time. Using
            // CommissionResource::getUrl() (already correctly done in
            // the sibling ListCommissionPaid widget) generates the URL
            // through Filament itself, so it always includes whatever
            // panel prefix is actually configured.
            ->recordUrl(fn ($record) => CommissionResource::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('edit')
                    ->label(false)
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => CommissionResource::getUrl('view', ['record' => $record]))
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
                        $settledCount = 0;
                        foreach ($records as $record) {
                            // Only the still-PENDING transactions —
                            // previously this marked every transaction
                            // on the wallet as paid regardless of
                            // status, which was harmless the first time
                            // (nothing to double-settle yet) but would
                            // have silently no-op'd on rows already paid
                            // in an earlier settlement while doing
                            // nothing to guarantee it couldn't touch
                            // them again if this action ever changed.
                            $pending = $record->transactions()->where('status', CommissionTransaction::PENDING)->get();
                            if ($pending->isEmpty()) {
                                continue;
                            }

                            foreach ($pending as $transaction) {
                                $transaction->status = CommissionTransaction::PAID;
                                $transaction->is_paid = true;
                                $transaction->paid_at = now();
                                $transaction->paid_by = auth()->id();
                                $transaction->save();

                                // Per-transaction audit record — this
                                // table already existed (migration ran
                                // fine) but its model class was never
                                // created, so CommissionTransaction::
                                // payments() threw "Class not found"
                                // the moment anything tried to load it.
                                CommissionPayment::create([
                                    'commission_id' => $record->id,
                                    'commission_transaction_id' => $transaction->id,
                                    'is_paid' => true,
                                    'paid_at' => now(),
                                    'paid_by' => auth()->id(),
                                    'amount' => $transaction->final_amount,
                                    'status' => CommissionPayment::PAID,
                                    'created_by' => auth()->id(),
                                ]);
                            }

                            // Kept as an informational "last known
                            // state" field, not something any list query
                            // relies on for filtering anymore — recomputed
                            // from whether any pending transactions
                            // actually remain, rather than being set
                            // once and left stale.
                            $record->status = $record->transactions()->where('status', CommissionTransaction::PENDING)->exists()
                                ? Commission::PENDING
                                : Commission::PAID;
                            $record->save();

                            // Same dual-channel pattern used for every
                            // other rider/merchant-facing notification —
                            // the Laravel Notifiable `notifications`
                            // table, read by the app's own notification
                            // screen.
                            try {
                                $amount = number_format($pending->sum('final_amount'), 2);
                                if ($record->user) {
                                    $record->user->notify(new \App\Notifications\CommissionSettled($record, $amount));
                                }
                            } catch (\Throwable $th) {
                                \Log::error('Failed to send commission settlement notification', ['error' => $th->getMessage(), 'commission_id' => $record->id]);
                            }

                            $settledCount++;
                        }
                        Notification::make()
                            ->title("Settled $settledCount user(s)' pending commission.")
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