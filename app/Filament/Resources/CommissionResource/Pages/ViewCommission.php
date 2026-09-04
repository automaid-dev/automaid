<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use App\Models\CommissionTransaction;
use App\Models\Commission;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Notifications\Notification;

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
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);
    }

}
