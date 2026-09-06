<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use App\Models\CommissionTransaction;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementDeduction;
use App\Models\CommissionPayment;
use App\Models\Commission;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class ViewCommission extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    public int|string|null $commissionId = null;

    public $record = null;

    /**
     * [mount description]
     * @return [type] [description]
     */
    public function mount(): void
    {
        parent::mount();
        $this->commissionId = request()->route('record'); // 'record' matches the {record} in getPages()
        $this->record = Commission::find($this->commissionId);
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->user->name . ' (' . ucfirst($this->record->user->roles->first()?->name) . ')';        
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->user->name . ' (' . ucfirst($this->record->user->roles->first()?->name) . ')';
    }

    /**
     * Read-only history of past payouts for this user — separate from
     * the transactions table below since a ListRecords page can only
     * host one Filament table; a modal keeps this on the same page
     * without needing a whole new Resource just to browse settlements.
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewSettlements')
                ->label('Settlement History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Settlement History')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function () {
                    $settlements = CommissionSettlement::where('user_id', $this->record->user_id)
                        ->with('deductions')
                        ->latest('paid_at')
                        ->get();
                    return view('filament.commission-settlement-history', ['settlements' => $settlements]);
                }),
        ];
    }

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public function table(Table $table): Table
    {
        $query = CommissionTransaction::query()
            ->where('commission_id', $this->commissionId)
            ->latest();

        $hasPaid = $query->clone()->where('status', '!=', 'pending')->exists();

        $columns = [
            TextColumn::make('no')->label('No')->rowIndex(),
            TextColumn::make('order.id')
                ->label('Order ID')
                ->getStateUsing(fn ($record) => $record->order?->id ?? '-')
                ->searchable(),
            TextColumn::make('order.created_at')
                ->label('Order Date')
                ->getStateUsing(fn ($record) => $record->order?->created_at
                    ? \Carbon\Carbon::parse($record->order->created_at)->format('d M Y')
                    : '-'),
        ];

        if ($hasPaid) {
            $columns[] = TextColumn::make('paid_at')
                ->label('Payout Date')
                ->getStateUsing(fn ($record) =>
                    $record->paid_at
                        ? \Carbon\Carbon::parse($record->paid_at)->format('d M Y')
                        : '-'
                );
        }

        $columns[] = TextColumn::make('final_amount')
            ->label('Payout Amount (RM)');

        $columns[] = TextColumn::make('status')
            ->badge()
            ->formatStateUsing(fn (string $state): string => strtoupper($state))
            ->color(fn (string $state): string => match ($state) {
                'paid' => 'success',
                'pending' => 'primary',
                default => 'gray',
            })
            ->label('Payout Status');

        return $table->query($query)
            ->columns($columns)
            ->recordUrl(null)
            ->checkIfRecordIsSelectableUsing(
                fn (CommissionTransaction $record): bool => $record->status === CommissionTransaction::PENDING,
            )
            ->actions([
                Action::make('edit')
                    ->label(false)
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Update Commission Amount')
                    ->modalWidth('lg')
                    ->modalButton('Save')
                    ->form([
                        TextInput::make('final_amount')
                            ->label('Commission Amount (RM)*')
                            ->required()
                            ->numeric(),
                    ])
                    // Previously the amount shown when opening this
                    // modal relied on the field's own ->default()
                    // callback — for a plain row Action (not
                    // Filament's dedicated EditAction), that's not
                    // reliably re-evaluated fresh each time the modal
                    // opens, so clicking edit on one row could show an
                    // amount left over from whichever OTHER row's
                    // modal was opened last, not the row actually
                    // clicked. ->fillForm() is the documented way to
                    // force the form to load fresh from the specific
                    // record every time this action is triggered.
                    ->fillForm(fn (CommissionTransaction $record): array => [
                        'final_amount' => $record->final_amount ?? 0,
                    ])
                    ->action(function (CommissionTransaction $record, array $data) {
                        $record->update([
                            'final_amount' => $data['final_amount'],
                        ]);

                        // Update balance in commissions
                        $commission = $record->commission; // relation from CommissionTransaction to Commission
                        if ($commission) {
                            
                            // Recalculate balance (sum of all pending/paid transaction amounts, etc.)
                            $newBalance = $commission->transactions()->sum('final_amount');

                            $commission->update([
                                'balance' => $newBalance,
                            ]);
                        }
                        Notification::make()
                            ->title('Commission amount updated.')
                            ->success()
                            ->send();                           
                    })

            ])
            ->bulkActions([
                BulkAction::make('settle')
                    ->label('Settle Selected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    // checkIfRecordIsSelectableUsing above already keeps
                    // paid rows from being checked in the UI, but the
                    // filter here is a second, server-side guard against
                    // ever settling an already-paid transaction — e.g.
                    // if a row was checked, then paid via the edit
                    // action or another tab, before this action fires.
                    ->form([
                        TextInput::make('bank_transaction_id')
                            ->label('Bank / Transfer Transaction ID*')
                            ->required()
                            ->helperText('The reference from the actual bank transfer or e-wallet payout — required for audit.'),
                        Repeater::make('deductions')
                            ->label('Deductions (optional)')
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options(CommissionSettlementDeduction::types())
                                    ->required(),
                                TextInput::make('description')
                                    ->label('Description'),
                                TextInput::make('amount')
                                    ->label('Amount (RM)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add deduction')
                            ->reorderable(false),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records = $records->where('status', CommissionTransaction::PENDING);
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Nothing to settle — every selected row is already paid.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $firstTransaction = $records->first();
                        $commission = $firstTransaction->commission;
                        $user = $commission?->user;
                        if (!$user) {
                            Notification::make()
                                ->title('Could not determine the user for this commission wallet.')
                                ->danger()
                                ->send();
                            return;
                        }
                        $role = $user->rider ? 'rider' : ($user->merchant ? 'merchant' : 'unknown');

                        $gross = (float) $records->sum('final_amount');
                        $deductionsInput = $data['deductions'] ?? [];
                        $totalDeductions = (float) collect($deductionsInput)->sum('amount');
                        $net = $gross - $totalDeductions;

                        $now = now();
                        $adminId = auth()->id();

                        $settlement = CommissionSettlement::create([
                            'user_id' => $user->id,
                            'role' => $role,
                            'gross_amount' => $gross,
                            'total_deductions' => $totalDeductions,
                            'net_amount' => $net,
                            'bank_transaction_id' => $data['bank_transaction_id'],
                            'notes' => $data['notes'] ?? null,
                            'paid_at' => $now,
                            'paid_by' => $adminId,
                        ]);

                        foreach ($deductionsInput as $deduction) {
                            $settlement->deductions()->create([
                                'type' => $deduction['type'],
                                'description' => $deduction['description'] ?? null,
                                'amount' => $deduction['amount'],
                            ]);
                        }

                        foreach ($records as $transaction) {
                            $transaction->update([
                                'status' => CommissionTransaction::PAID,
                                'is_paid' => true,
                                'paid_at' => $now,
                                'paid_by' => $adminId,
                                'commission_settlement_id' => $settlement->id,
                            ]);

                            CommissionPayment::create([
                                'commission_id' => $transaction->commission_id,
                                'commission_transaction_id' => $transaction->id,
                                'commission_settlement_id' => $settlement->id,
                                'is_paid' => true,
                                'paid_at' => $now,
                                'paid_by' => $adminId,
                                'amount' => $transaction->final_amount,
                                'status' => CommissionPayment::PAID,
                                'created_by' => $adminId,
                            ]);
                        }

                        Notification::make()
                            ->title('Settled RM' . number_format($net, 2) . ' across ' . $records->count() . ' transaction(s)')
                            ->body('Bank reference: ' . $data['bank_transaction_id'])
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

}
