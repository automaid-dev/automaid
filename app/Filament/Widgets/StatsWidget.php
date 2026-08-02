<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Subscription;
use App\Models\User;

class StatsWidget extends BaseWidget
{
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected $listeners = ['updateStats' => 'updateDateRange'];

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
     * [updateDateRange description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function updateDateRange($data)
    {
        $this->startDate = $data['startDate'];
        $this->endDate = $data['endDate'];
        $this->dispatch('$refresh'); // <- Force re-render
    }

    /**
     * [getTotalSales description]
     * @return [type] [description]
     */
    public function getTotalSales() {
        $types = [
            Order::SUBSCRIPTION,
            Order::PURCHASE_BAG,
            Order::BOOKING,
        ];
        return Order::whereIn('order_type', $types)
            ->where(['status' => Order::PAID])
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->sum('grand_total');
    }

    /**
     * [getPendingAssign description]
     * @return [type] [description]
     */
    public function getPendingAssign()
    {
        $status = [
            OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE,
            OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE,
        ];
        $orders = OrderStatus::whereIn('code', $status)->whereHas('order', function($query) {
            $query->where(['status' => Order::PAID]);
        })
        ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
        ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
        ->count();
        return $orders;
    }

    /**
     * [getPendingApproval description]
     * @return [type] [description]
     */
    public function getPendingApproval()
    {
        $users = User::where(['status' => User::ONBOARDING])
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['customer']);
            })
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->count();
        return $users;
    }

    /**
     * [getTotalOrders description]
     * @return [type] [description]
     */
    public function getTotalOrders()
    {
        return Order::where(['order_type' => Order::BOOKING])
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->paid()->count();
    }

    /**
     * [getTotalSubscriptions description]
     * @return [type] [description]
     */
    public function getTotalSubscriptions()
    {
        return Subscription::active()
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->count();
    }

    /**
     * [getTotalbagOrdered description]
     * @return [type] [description]
     */
    public function getTotalbagOrdered()
    {
        return Order::where(['order_type' => Order::PURCHASE_BAG])
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->paid()->count();
    }

    /**
     * [getStats description]
     * @return [type] [description]
     */
    protected function getStats(): array
    {
        return [
            Card::make('Sales (RM)', number_format($this->getTotalSales(), 2)),
            Card::make('Pending to assign', number_format($this->getPendingAssign())),
            Card::make('Pending approval', number_format($this->getPendingApproval())),
            Card::make('Total orders', number_format($this->getTotalOrders())),
            Card::make('Total subscriptions', number_format($this->getTotalSubscriptions())),
            Card::make('Total bags ordered', number_format($this->getTotalbagOrdered())),
        ];
    }
}
