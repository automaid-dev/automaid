<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 3;

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

    /**
     * [table description]
     * @param  Table  $table [description]
     * @return [type]        [description]
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['user', 'recurring_active'])
                ->withExists(['renewalCharges as has_pending_charge' => fn ($c) => $c->whereIn('status', ['pending', 'accepted'])]))
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(), 
                TextColumn::make('id')
                    ->label('Subscription ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->default('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => ucfirst(strtolower($state)))
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        'inactive' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('next_renewal')
                    ->label('Next Renewal')
                    ->state(fn (Subscription $record) => $record->nextRenewalDate())
                    ->formatStateUsing(fn ($state) => $state?->format('d M Y'))
                    ->placeholder('-')
                    ->badge()
                    ->color(fn (Subscription $record): string => match (true) {
                        (bool) $record->has_pending_charge => 'warning',
                        $record->nextRenewalDate()?->lt(today()) ?? false => 'danger',
                        $record->nextRenewalDate()?->lte(today()->addDays(3)) ?? false => 'warning',
                        default => 'gray',
                    })
                    ->description(function (Subscription $record): ?string {
                        if ($record->status !== Subscription::ACTIVE) {
                            return null;
                        }
                        if (!$record->recurring_active) {
                            return 'No card on file — will not auto-renew';
                        }
                        $gateway = \App\Models\Transaction::gatewayLabel($record->payment_gateway);
                        if ($record->has_pending_charge) {
                            return "{$gateway} charge pending — check renewal";
                        }
                        $date = $record->nextRenewalDate();
                        if ($date && $date->lt(today())) {
                            return "{$gateway} · overdue (retrying)";
                        }
                        $days = $date ? (int) today()->diffInDays($date) : null;
                        return $gateway . ' · ' . match (true) {
                            $days === 0 => 'today',
                            $days === 1 => 'tomorrow',
                            default => "in {$days} days",
                        };
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy(
                        \App\Models\PaymentRecurring::select('next_payment_date')
                            ->whereColumn('payment_recurrings.subscription_id', 'subscriptions.id')
                            ->whereIn('status', ['subscription', 'subscription_renewal'])
                            ->where('status_payment', 'paid')
                            ->orderByDesc('id')
                            ->limit(1),
                        $direction
                    )),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                \App\Filament\Filters\DateRangeFilter::make('date_range', [
                    'created' => ['Subscribed date', \App\Filament\Filters\DateRangeFilter::column('subscriptions.created_at')],
                    'renewal' => ['Next renewal', fn ($q, $from, $until) => $q
                        ->where('subscriptions.status', Subscription::ACTIVE)
                        ->whereHas('recurring_active', fn ($r) => $r
                            ->when($from, fn ($r) => $r->whereDate('next_payment_date', '>=', $from))
                            ->when($until, fn ($r) => $r->whereDate('next_payment_date', '<=', $until)))],
                ]),
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Subscription::ACTIVE => 'Active',
                        Subscription::PENDING => 'Pending',
                        Subscription::CANCELLED => 'Cancelled',
                        Subscription::INACTIVE => 'Inactive',
                    ])
                    ->placeholder('All Statuses')
                    ->label('Status'),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([
                // Same approach as Orders: streams a CSV of exactly what the
                // table currently shows (filters, search, sort).
                Tables\Actions\Action::make('export')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn ($livewire) => static::exportCsv(
                        $livewire->getFilteredSortedTableQuery()->with(['user', 'recurring_active'])->get()
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 20, 50, 100]);

    }

    protected static function exportCsv($subscriptions): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $setting = \App\Models\Setting::find(1);
        $filename = 'subscriptions_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($subscriptions, $setting) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Subscription ID', 'Customer', 'Email', 'Phone', 'Plan', 'Payment Gateway', 'Status',
                'Subscribed At', 'Start Date', 'Paid Until', 'Next Renewal', 'Plan Price (RM)',
                'Orders Used This Cycle', 'Card', 'Sign-up Order ID',
            ]);
            foreach ($subscriptions as $sub) {
                $card = $sub->recurring_active;
                fputcsv($handle, [
                    $sub->id,
                    $sub->user->name ?? '-',
                    $sub->user->email ?? '-',
                    $sub->user->mobile_no ?? '-',
                    $sub->plan_code ? Subscription::planLabel($sub->plan_code) : '-',
                    \App\Models\Transaction::gatewayLabel($sub->payment_gateway),
                    ucfirst((string) $sub->status),
                    optional($sub->created_at)->format('Y-m-d H:i'),
                    $sub->start_date ? \Carbon\Carbon::parse($sub->start_date)->format('Y-m-d') : '-',
                    $sub->end_date ? \Carbon\Carbon::parse($sub->end_date)->format('Y-m-d') : '-',
                    $sub->nextRenewalDate()?->format('Y-m-d') ?? '-',
                    $setting ? number_format($sub->planPrice($setting), 2) : '-',
                    (int) $sub->orders_used_current_cycle,
                    $card && $card->cc_last_four ? trim(($card->cc_brand ?? '') . ' ****' . $card->cc_last_four) : ($card ? 'Saved' : '-'),
                    $sub->order_id ?? '-',
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
            'view' => Pages\ViewSubscription::route('/{record}/{user}/view'), 
        ];
    }


}
