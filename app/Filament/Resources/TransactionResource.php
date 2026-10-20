<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    /**
     * [canCreate description]
     * @return [type] [description]
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['payment', 'order.user']))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('created_at')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state->format('d M Y'))                    
                    ->label('Date'),
                TextColumn::make('id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(function (string $state): string {
                        $labels = [
                            Transaction::PURCHASE_BAG => 'BAG PURCHASE',
                            Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
                            Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
                            Transaction::BOOKING => 'BOOKING',
                        ];
                        return $labels[$state] ?? strtoupper(str_replace('_', ' ', $state));
                    }),
                TextColumn::make('gateway')
                    ->label('Payment Gateway')
                    ->badge()
                    ->state(fn (Transaction $record): string => $record->gatewayCode())
                    ->formatStateUsing(fn (string $state): string => Transaction::gatewayLabel($state))
                    ->color(fn (string $state): string => match ($state) { 'gkash' => 'warning', 'revenuemonster' => 'success', default => 'info' }),
                TextColumn::make('amount')
                    ->label('Total Amount (RM)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'primary',
                        'refunded' => 'danger',
                        default => 'gray',
                    })
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        Transaction::BOOKING => 'BOOKING',
                        Transaction::SUBSCRIPTION => 'SUBSCRIPTION',
                        Transaction::SUBSCRIPTION_RENEWAL => 'SUBSCRIPTION RENEWAL',
                        Transaction::PURCHASE_BAG => 'BAG PURCHASE',
                    ])
                    ->placeholder('All Types')
                    ->label(false),
                SelectFilter::make('gateway')
                    ->options(['fiuu' => 'Fiuu', 'gkash' => 'GKash', 'revenuemonster' => 'Revenue Monster'])
                    ->placeholder('All Gateways')
                    ->label(false)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->gateway($data['value'])
                        : $query),
                \App\Filament\Filters\DateRangeFilter::make('date_range', [
                    'created' => ['Date', \App\Filament\Filters\DateRangeFilter::column('transactions.created_at')],
                ]),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions([
                // Same approach as Orders: streams a CSV of exactly what the
                // table currently shows (filters, search, sort).
                Tables\Actions\Action::make('export')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn ($livewire) => static::exportCsv(
                        $livewire->getFilteredSortedTableQuery()->with(['payment', 'order.user'])->get()
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-magnifying-glass')
                    ->url(fn ($record) => \App\Filament\Resources\TransactionResource\Pages\ViewTransaction::getUrl(['record' => $record->getKey()]))
                    ->openUrlInNewTab(false)
                    ->modal(false),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }

    protected static function exportCsv($transactions): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $types = [
            Transaction::PURCHASE_BAG => 'Bag Purchase',
            Transaction::SUBSCRIPTION => 'Subscription',
            Transaction::SUBSCRIPTION_RENEWAL => 'Subscription Renewal',
            Transaction::BOOKING => 'Booking',
        ];
        $filename = 'transactions_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($transactions, $types) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Transaction ID', 'Date', 'Order ID', 'Order No', 'Customer', 'Email',
                'Type', 'Payment Gateway', 'Amount (RM)', 'Status',
            ]);
            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->id,
                    optional($t->created_at)->format('Y-m-d H:i'),
                    $t->order_id ?? '-',
                    $t->order->series_no ?? '-',
                    $t->order->user->name ?? '-',
                    $t->order->user->email ?? '-',
                    $types[$t->type] ?? ucwords(str_replace('_', ' ', (string) $t->type)),
                    Transaction::gatewayLabel($t->gatewayCode()),
                    number_format((float) $t->amount, 2, '.', ''),
                    ucfirst((string) $t->status),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
            'view' => Pages\ViewTransaction::route('/{record}/view'),
        ];
    }


}

