<?php

namespace App\Filament\Resources\CommissionResource\Widgets;

use App\Filament\Resources\CommissionResource\Pages\ListCommissions;
// use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Commission;
use App\Models\CommissionTransaction;
use Carbon\Carbon;

class CommissionStats extends BaseWidget
{
    // use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    /**
     * [getStats description]
     * @return [type] [description]
     */
    protected function getStats(): array
    {
        // Get current month range
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Total commission paid this month (from transactions table)
        $totalPaid = CommissionTransaction::where('amount', '>', 0)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereHas('commission', function ($query) {
                $query->where('status', 'paid');
            })
            ->sum('amount');

        // Total pending payout (from ewallet table with 'pending' status)
        $totalPending = Commission::where('status', 'pending')
            ->withSum(['transactions' => fn($q) => $q->whereNotNull('amount')], 'amount')
            ->get()
            ->sum('transactions_sum_amount');

        return [
            Stat::make('Total commission paid this month', 'RM ' . number_format($totalPaid, 2)),
            Stat::make('Total pending payout', 'RM ' . number_format($totalPending, 2)),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full'; // Fix for rendering in Blade
    }
}