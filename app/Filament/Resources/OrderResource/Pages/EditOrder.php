<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Activity;
use App\Models\AssignJob;
use App\Models\BagReceive;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderComplete;
use App\Models\OrderPickup;
use App\Models\OrderStatus;
use App\Models\PickupOutlet;
use App\Models\Status;
use App\Models\User;
use App\Models\WashComplete;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Cancel Order')
                ->modalHeading('Cancel this Order?')
                ->modalDescription('Are you sure you want to cancel this order? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Cancel Order')
                ->action(function () {

                    // update status order
                    $this->record->status = Order::CANCELLED;
                    $this->record->updated_by = auth()->user()->id;
                    $this->record->save();

                    // Refund the subscription quota slot this order
                    // consumed, if any — without this, a cancelled
                    // order permanently cost the customer a free-wash
                    // slot they never actually used.
                    if ($this->record->used_subscription_quota) {
                        $subscription = \App\Models\Subscription::where('user_id', $this->record->user_id)
                            ->where('status', \App\Models\Subscription::ACTIVE)
                            ->first();
                        if ($subscription && $subscription->orders_used_current_cycle > 0) {
                            $subscription->orders_used_current_cycle = $subscription->orders_used_current_cycle - 1;
                            $subscription->save();
                        }
                    }

                    // update status booking
                    $booking = $this->record->booking;
                    $booking->status = Booking::CANCEL;
                    $booking->updated_by = auth()->user()->id;
                    $booking->save();

                    // check rider
                    $rider_id = null;
                    if ($this->record->rider) {
                        $rider_id = $this->record->rider->accepted_by;
                    }

                    // check merchant
                    $merchant_id = null;
                    if ($this->record->merchant) {
                        $merchant_id = $this->record->merchant->accepted_by;
                    }

                    // insert activity
                    $roles = [User::CUSTOMER, User::RIDER, User::MERCHANT];
                    foreach ($roles as $role) {
                        Activity::updateOrCreate(
                            [
                                'order_id' => $this->record->id, 
                                'user_id' => $this->record->user_id,
                                'transaction_id' => $this->record->transaction->id ?? null, 
                                'user_type' => $role,
                                'rider_id' => $role == User::RIDER ? $rider_id : null,
                                'merchant_id' => $role == User::MERCHANT ? $merchant_id : null,
                                'title' => 'Cancel Order', 
                                'status' => Activity::ACTIVE
                            ],
                        );
                    }

                    // send pn to customer
                    event(new \App\Events\CustomerAdminCancelOrder($this->record->user, $this->record));

                    // send pn to rider
                    if ($this->record->rider) {
                        event(new \App\Events\RiderAdminCancelOrder($this->record->rider->user, $this->record));
                    }

                    // send pn to merchant
                    if ($this->record->merchant) {
                        event(new \App\Events\MerchantAdminCancelOrder($this->record->merchant->user, $this->record));
                    }

                    Notification::make()
                        ->title('Order has been cancelled.')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status !== 'cancelled'),
        ];
    }

    /**
     * [getTitle description]
     * @return [type] [description]
     */
    public function getTitle(): string
    {
        return $this->record->user->name . ' — Order #' . $this->record->id . ' (' . $this->record->series_no . ')';
    }

    /**
     * [getBreadcrumb description]
     * @return [type] [description]
     */
    public function getBreadcrumb(): string
    {
        return $this->record->user->name . ' (' . $this->record->series_no . ')';        
    }

    /**
     * [mount description]
     * @param  int    $record [description]
     * @return [type]         [description]
     */
    public function mount(string|int $record): void
    {
        parent::mount($record);
        $this->form->fill([
            'rider_id' => $this->record?->rider?->accepted_user?->id,
            'merchant_id' => $this->record?->merchant?->accepted_user?->id,
            'customer_status' => $this->record?->customer_latest_status?->code,
            'rider_status' => $this->record?->rider_latest_status?->code,
            'merchant_status' => $this->record?->merchant_latest_status?->code,
            'admin_status' => $this->record?->admin_status,
        ]);
    }

    /**
     * [getRedirectUrl description]
     * @return [type] [description]
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record->id]);
    }

    /**
     * [mutateFormDataBeforeSave description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // update assigned rider
        if (isset($data['rider_id'])) {

            // update existing rider
            if ($this->record->rider) {
                $this->record->rider->is_accepted = true;
                $this->record->rider->accepted_by = $data['rider_id'];
                $this->record->rider->accepted_at = now();
                $this->record->rider->updated_by = auth()->user()->id;
                $this->record->rider->updated_at = now();
                $this->record->rider->save();                
            }

            // assign new rider
            else {

                if ($this->record->rider_pending) {
                    if ($this->record->rider_pending->user_id == $data['rider_id']) {
                        // Re-assigning the SAME rider who already has a
                        // pending, not-yet-accepted job — previously this
                        // force-set is_accepted=true here, silently
                        // accepting on the rider's behalf without them
                        // ever tapping anything, which is exactly what
                        // made the job vanish from their Incoming tab.
                        // Just keep it visible as a genuine pending job —
                        // leave acceptance to the rider.
                        $this->record->rider_pending->is_queue = true;
                        $this->record->rider_pending->updated_by = auth()->user()->id;
                        $this->record->rider_pending->save();
                    } else {
                        // Switching to a DIFFERENT rider while a pending
                        // job exists for the old one — retire the stale
                        // job and create a fresh pending job for the new
                        // rider, rather than mutating the old job in place
                        // (which previously left user_id pointing at the
                        // old rider while accepted_by pointed at the new
                        // one — a genuine data mismatch).
                        $this->record->rider_pending->is_queue = false;
                        $this->record->rider_pending->updated_by = auth()->user()->id;
                        $this->record->rider_pending->save();

                        $status = OrderStatus::firstOrCreate(
                            [
                                'order_id' => $this->record->id, 
                                'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, 
                                'is_done' => true,
                                'done_at' => now(),
                                'created_by' => auth()->user()->id,
                            ]
                        );
                        $job = new AssignJob();
                        $job->code = OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE;
                        $job->user_id = $data['rider_id'];
                        $job->order_id = $this->record->id;
                        $job->order_status_id = $status->id;
                        $job->is_queue = true;
                        $job->is_accepted = false;
                        $job->save();

                        // notify the newly-assigned rider — this job
                        // needs their acceptance and previously nothing
                        // told them it existed.
                        if ($job->user) {
                            event(new \App\Events\RiderAdminAssignOrder($job->user, $job));
                        }
                    }
                }
                else {

                    // assign accepted rider
                    $status = OrderStatus::firstOrCreate(
                        [
                            'order_id' => $this->record->id, 
                            'code' => OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, 
                            'is_done' => true,
                            'done_at' => now(),
                            'created_by' => auth()->user()->id,
                        ]
                    );
                    // Leave this as a genuine pending job (is_accepted =
                    // false, is_queue = true) — same as the auto-assign
                    // flow — rather than marking it pre-accepted. Marking
                    // it pre-accepted here used to skip the rider's own
                    // acceptance step entirely, which meant the
                    // "ready for pickup" follow-up job (normally created
                    // when the rider taps Accept in their own app) never
                    // got created either — so nothing ever showed up in
                    // their dashboard at all. The rider now sees this as
                    // a normal incoming job and accepts it themselves,
                    // which correctly cascades to the next step.
                    $job = new AssignJob();
                    $job->code = OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE;
                    $job->user_id = $data['rider_id'];
                    $job->order_id = $this->record->id;
                    $job->order_status_id = $status->id;
                    $job->is_queue = true;
                    $job->is_accepted = false;
                    $job->save();

                    // notify the newly-assigned rider — this job needs
                    // their acceptance and previously nothing told them
                    // it existed.
                    if ($job->user) {
                        event(new \App\Events\RiderAdminAssignOrder($job->user, $job));
                    }
                }
            }
        }

        // update merchant
        if (isset($data['merchant_id'])) {

            // update assigned merchant
            if ($this->record->merchant) {
                $this->record->merchant->is_accepted = true;
                $this->record->merchant->accepted_by = $data['merchant_id'];
                $this->record->merchant->accepted_at = now();
                $this->record->merchant->updated_by = auth()->user()->id;
                $this->record->merchant->updated_at = now();
                $this->record->merchant->save();
            }

            // assign new merchant
            else {
                if ($this->record->merchant_pending) {
                    if ($this->record->merchant_pending->user_id == $data['merchant_id']) {
                        // Re-assigning the SAME merchant who already has a
                        // pending, not-yet-accepted job — previously this
                        // force-set is_accepted=true here, silently
                        // accepting on the merchant's behalf without them
                        // ever tapping anything. That's exactly what made
                        // the job vanish from their Incoming tab (which
                        // filters on is_accepted=false). Just ensure it's
                        // still visible as a genuine pending job instead —
                        // leave acceptance to the merchant.
                        $this->record->merchant_pending->is_queue = true;
                        $this->record->merchant_pending->updated_by = auth()->user()->id;
                        $this->record->merchant_pending->save();
                    } else {
                        // Switching to a DIFFERENT merchant while a pending
                        // job exists for the old one — the old job is now
                        // stale (previously this incorrectly mutated it in
                        // place, setting accepted_by to the NEW merchant's
                        // id while user_id stayed pointing at the OLD one,
                        // a genuine data-integrity mismatch). Retire it and
                        // create a fresh pending job for the new merchant,
                        // matching the brand-new-assignment path below.
                        $this->record->merchant_pending->is_queue = false;
                        $this->record->merchant_pending->updated_by = auth()->user()->id;
                        $this->record->merchant_pending->save();

                        $status = OrderStatus::firstOrCreate(
                            [
                                'order_id' => $this->record->id, 
                                'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE, 
                                'is_done' => true,
                                'done_at' => now(),
                                'created_by' => auth()->user()->id,
                            ]
                        );
                        $job = new AssignJob();
                        $job->code = OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE;
                        $job->user_id = $data['merchant_id'];
                        $job->order_id = $this->record->id;
                        $job->order_status_id = $status->id;
                        $job->is_queue = true;
                        $job->is_accepted = false;
                        $job->save();

                        // notify the newly-assigned merchant — this job
                        // needs their acceptance and previously nothing
                        // told them it existed.
                        if ($job->user) {
                            event(new \App\Events\MerchantAdminAssignOrder($job->user, $job));
                        }
                    }
                }
                else {

                    // assign accepted merchant
                    $status = OrderStatus::firstOrCreate(
                        [
                            'order_id' => $this->record->id, 
                            'code' => OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE, 
                            'is_done' => true,
                            'done_at' => now(),
                            'created_by' => auth()->user()->id,
                        ]
                    );
                    // See the matching rider block above for why this is
                    // left as a genuine pending job rather than marked
                    // pre-accepted.
                    $job = new AssignJob();
                    $job->code = OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE;
                    $job->user_id = $data['merchant_id'];
                    $job->order_id = $this->record->id;
                    $job->order_status_id = $status->id;
                    $job->is_queue = true;
                    $job->is_accepted = false;
                    $job->save();

                    // notify the newly-assigned merchant — this job
                    // needs their acceptance and previously nothing told
                    // them it existed.
                    if ($job->user) {
                        event(new \App\Events\MerchantAdminAssignOrder($job->user, $job));
                    }
                }
            }
        }

        // remove from pending assign if rider & merchant is assigned
        if (isset($data['rider_id']) && isset($data['merchant_id'])) {
            $this->record->is_pending_assign = false;
            $this->record->save();
        }

        // update status admin
        //
        // IMPORTANT: `admin_status` is present in $data on every save,
        // not just when it's actually changed — Filament submits the
        // full form state regardless. This block used to run on every
        // save unconditionally, wiping every order_status/assign_job/
        // activity/etc and rebuilding from whatever the CURRENT (often
        // unrelated-to-this-save) admin_status value pointed to — using
        // $data['rider_id']/$data['merchant_id'], which are null unless
        // the admin is ALSO picking a rider/merchant in that exact same
        // save. That's why simply assigning a rider (with admin_status
        // unchanged) would wipe the assignment right back out, and why
        // trying again afterward kept failing — the same wipe fires on
        // every subsequent save too.
        //
        // Fixed: only wipe-and-rebuild when admin_status genuinely
        // changed, and fall back to the existing accepted rider/merchant
        // when rebuilding so a save that doesn't touch the dropdowns
        // never silently drops an assignment.
        $statusActuallyChanged = isset($data['admin_status'])
            && $data['admin_status'] !== $this->record->admin_status;

        if ($statusActuallyChanged) {

            $riderIdForRebuild = $data['rider_id']
                ?? $this->record->rider?->accepted_by
                ?? $this->record->rider_pending?->accepted_by
                ?? null;
            $merchantIdForRebuild = $data['merchant_id']
                ?? $this->record->merchant?->accepted_by
                ?? $this->record->merchant_pending?->accepted_by
                ?? null;
            $data['rider_id'] = $riderIdForRebuild;
            $data['merchant_id'] = $merchantIdForRebuild;

            // remove all status
            $this->record->order_statuses()->delete();

            // remove all assign jobs 
            $this->record->assign_jobs()->delete();

            // remove all activities
            $this->record->activities()->delete();
            
            // delete pickup outlets
            $this->record->pickup_outlets()->delete();

            // delete wash completes
            $this->record->wash_completes()->delete();

            // delete bag receives
            $this->record->bag_receives()->delete();

            // delete order pickups
            $this->record->order_pickups()->delete();

            // delete order complete
            $this->record->delivered()->delete();

            // select status booking created
            if ($data['admin_status'] == Order::MANUAL_BOOKING_CREATED) { 

                $codes = ['01', '11', '21'];

                // add status pending
                $this->insertOrderStatusPending($codes, $data['rider_id'], $data['merchant_id']);

                // add activities
                $this->insertActivity('customer', 'Booking');

                // update booking status
                $this->record->booking->status = Booking::ACTIVE;
                $this->record->booking->updated_by = auth()->user()->id;                
                $this->record->booking->save();             
            }

            // select status rider & merchant accept job
            if ($data['admin_status'] == Order::MANUAL_RIDER_MERCHANT_ACCEPT_JOB) { 

                // add status done
                $done = ['01', '11', '21'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);     

                // add status pending
                $pending = ['12', '02', '22'];
                $this->insertOrderStatusPending($pending, $data['rider_id'], $data['merchant_id']);           
            }

            // select status rider picked up bag from customer
            if ($data['admin_status'] == Order::MANUAL_RIDER_PICKUP_BAG_CUSTOMER) { 

                // add status done
                $done = ['01', '11', '21', '12', '02', '22'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);

                // add status pending
                $pending = ['13'];
                $this->insertOrderStatusPending($pending, $data['rider_id'], $data['merchant_id']);

                // insert order pickup
                OrderPickup::firstOrCreate([
                    'order_id' => $this->record->id, 
                    'status' => OrderPickup::DELIVERY_TO_WASH_OUTLET,
                    'created_by' => auth()->user()->id,
                ]);

                // update booking status
                $this->record->booking->status = Booking::OUTLET;
                $this->record->booking->updated_by = auth()->user()->id;
                $this->record->booking->save();
            }

            // select status wash in progress & bag received
            if ($data['admin_status'] == Order::MANUAL_WASH_IN_PROGRESS_BAG_RECEIVED) { 

                // add status done
                $done = ['01', '11', '21', '12', '02', '22', '13'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);

                // add status pending
                $pending = ['23', '03', '17'];
                $this->insertOrderStatusPending($pending, $data['rider_id'], $data['merchant_id']);

                // insert bag receive
                BagReceive::firstOrCreate([
                    'order_id' => $this->record->id, 
                    'status' => BagReceive::WASH_IN_PROGRESS,
                    'created_by' => auth()->user()->id,
                ]);

                // update booking status
                $this->record->booking->status = Booking::OUTLET;
                $this->record->booking->updated_by = auth()->user()->id;
                $this->record->booking->save();
            }

            // select status merchant wash completed
            if ($data['admin_status'] == Order::MANUAL_MERCHANT_WASH_COMPLETED) { 

                // add status done
                $done = ['01', '11', '21', '12', '02', '22', '13', '23', '03', '17'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);

                // add status pending
                $pending = ['24', '14'];
                $this->insertOrderStatusPending($pending, $data['rider_id'], $data['merchant_id']);

                // insert wash complete
                WashComplete::firstOrCreate([
                    'order_id' => $this->record->id, 
                    'status' => WashComplete::WASH_COMPLETED,
                    'created_by' => auth()->user()->id,
                ]);

                // update booking status
                $this->record->booking->status = Booking::WASH;
                $this->record->booking->updated_by = auth()->user()->id;
                $this->record->booking->save();
            }

            // select status rider order picked up & out for delivery
            if ($data['admin_status'] == Order::MANUAL_RIDER_ORDER_PICKUP_OUT_DELIVERY) { 

                // add status done
                $done = ['01', '11', '21', '12', '02', '22', '13', '23', '03', '17', '24', '14'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);

                // add status pending
                $pending = ['04', '15', '25'];
                $this->insertOrderStatusPending($pending, $data['rider_id'], $data['merchant_id']);

                // insert pickup outlet
                PickupOutlet::firstOrCreate([
                    'order_id' => $this->record->id, 
                    'status' => PickupOutlet::PICKUP_OUTLET,
                    'created_by' => auth()->user()->id,
                ]);
            }

            // select status delivery completed & order delivery
            if ($data['admin_status'] == Order::MANUAL_DELIVERY_COMPLETED_ORDER_DELIVERED) { 

                // add status done
                $done = ['01', '11', '21', '12', '02', '22', '13', '23', '03', '17', '24', '14', '04', '15', '25', '05', '16', '26'];
                $this->insertOrderStatus($done, $data['rider_id'], $data['merchant_id']);

                // insert order complete
                OrderComplete::firstOrCreate([
                    'order_id' => $this->record->id, 
                    'status' => OrderComplete::DELIVERED,
                    'created_by' => auth()->user()->id,
                ]);

                // get booking info
                $booking = $this->record->booking;

                // update booking status
                $booking->status = Booking::CUSTOMER;
                $booking->updated_by = auth()->user()->id;
                $booking->save();
                
                // get  grand total
                $delivery_charge = $booking->delivery_charge ?? 0;
                $washing_charge = $booking->washing_charge ?? 0;
                $addon_charge = $booking->addon_charge ?? 0;
                $discount = $booking->discount ?? 0;
                $grand_total = ($washing_charge + $delivery_charge + $addon_charge) - $discount;

                // commission service
                $commission_service = new \App\Services\CommissionService();

                // set commission rider
                $user = User::find($data['rider_id']);
                $commission = $commission_service->getTotalCommission(User::RIDER, $user->rider->type_rider, $grand_total);
                if ($commission > 0) {

                    // insert commission rider
                    $commission_service->insertCommissionEwallet($commission, $user->id, $this->record->id);
                }

                // set commission merchant
                $user = User::find($data['merchant_id']);                
                $commission = $commission_service->getTotalCommission(User::MERCHANT, $user->merchant->type_merchant, $grand_total);
                if ($commission > 0) {

                    // insert commission merchant
                    $commission_service->insertCommissionEwallet($commission, $user->id, $this->record->id);
                }
            }

            // update order 
            $this->record->is_update_manually = true;
            $this->record->save();
        }

        foreach (['rider_id', 'merchant_id', 'customer_status', 'rider_status', 'merchant_status'] as $key) {
            unset($data[$key]);
        }
        return $data;        
    }

    /**
     * [insertActivity description]
     * @param  [type] $type  [description]
     * @param  [type] $title [description]
     * @return [type]        [description]
     */
    public function insertActivity($type, $title)
    {
        Activity::firstOrCreate(
            [
                'order_id' => $this->record->id, 
                'user_id' => $this->record->user_id, 
                'user_type' => $type,
                'title' => $title, 
                'status' => Activity::ACTIVE
            ],
        );            
        return true;
    }

    /**
     * [insertAssignJob description]
     * @param  [type] $code            [description]
     * @param  [type] $order_status_id [description]
     * @param  [type] $rider_id        [description]
     * @param  [type] $merchant_id     [description]
     * @return [type]                  [description]
     */
    public function insertAssignJob($code, $order_status_id, $rider_id, $merchant_id)
    {
        $customers = ['01', '02', '03', '04', '05'];
        $riders = ['11', '12', '13', '14', '15', '16', '17'];
        $merchants = ['21', '22', '23', '24', '25', '26'];

        if (in_array($code, $riders)) {
            $acceptedBy = $rider_id;
        } 
        elseif (in_array($code, $merchants)) {
            $acceptedBy = $merchant_id;
        } 
        elseif (in_array($code, $customers)) {
            $acceptedBy = $this->record->user_id;
        }
        else {
            $acceptedBy = null;
        }
        AssignJob::firstOrCreate(
            [
                'code' => $code, 
                'user_id' => $acceptedBy,
                'order_id' => $this->record->id, 
                'order_status_id' => $order_status_id,
                'is_accepted' => true, 
                'accepted_at' => now(), 
                'accepted_by' => $acceptedBy,
            ]
        );            
        return true;
    }

    /**
     * [insertOrderStatusPending description]
     * @param  [type] $codes       [description]
     * @param  [type] $rider_id    [description]
     * @param  [type] $merchant_id [description]
     * @return [type]              [description]
     */
    public function insertOrderStatusPending($codes, $rider_id, $merchant_id)
    {
        foreach ($codes as $code) {
            $order_status = OrderStatus::firstOrCreate(
                [
                    'order_id' => $this->record->id, 
                    'code' => $code, 
                    'created_by' => auth()->user()->id,                        
                ]
            );

            $customers = ['01', '02', '03', '04', '05'];
            $riders = ['11', '12', '13', '14', '15', '16', '17'];
            $merchants = ['21', '22', '23', '24', '25', '26'];

            if (in_array($code, $riders)) {
                $acceptedBy = $rider_id;
            } 
            elseif (in_array($code, $merchants)) {
                $acceptedBy = $merchant_id;
            } 
            elseif (in_array($code, $customers)) {
                $acceptedBy = $this->record->user_id;
            }
            else {
                $acceptedBy = null;
            }

            AssignJob::firstOrCreate(
                [
                    'code' => $code, 
                    'user_id' => $acceptedBy,
                    'order_id' => $this->record->id, 
                    'order_status_id' => $order_status->id,
                ],
                [
                    // Both rider and merchant home dashboards require
                    // is_queue=true for their own pending-acceptance code
                    // (11 for rider, 21 for merchant) before a job shows
                    // up at all — normally set by the auto-assignment
                    // command's nearest-candidate queueing. A manually
                    // assigned job never went through that, so without
                    // this the job exists in the database but is
                    // invisible in the rider/merchant app.
                    'is_queue' => true,
                    'is_accepted' => false,
                ]
            );
        }
        return true;
    }

    /**
     * [insertOrderStatus description]
     * @param  [type] $codes       [description]
     * @param  [type] $rider_id    [description]
     * @param  [type] $merchant_id [description]
     * @return [type]              [description]
     */
    public function insertOrderStatus($codes, $rider_id, $merchant_id) 
    {
        foreach ($codes as $code) {
            $order_status = OrderStatus::firstOrCreate(
                [
                    'order_id' => $this->record->id, 
                    'code' => $code, 
                    'is_done' => true, 
                    'done_at' => now(),
                    'created_by' => auth()->user()->id,
                ]
            );
            $this->insertAssignJob($code, $order_status->id, $rider_id, $merchant_id);
        }
        return true;
    }

    /**
     * [mutateFormDataBeforeFill description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function mutateFormDataBeforeFill(array $data): array
    {
        if ($record = static::getRecord()) {
        }
        return $data;
    }

    /**
     * [form description]
     * @param  Form   $form [description]
     * @return [type]       [description]
     */
    public function form(Form $form): Form
    {
        $statuses = \App\Models\Status::get();

        // customer status
        $customer_codes = ['01', '02', '03', '04', '05'];   
        $customer_status = $statuses
            ->whereIn('code', $customer_codes)
            ->sortBy('code')
            ->pluck('desc', 'code')
            ->toArray();

        $existingStatuses = OrderStatus::where('order_id', $this->record->id)
            ->whereIn('code', $customer_codes)
            ->where('is_done', true)
            ->whereNotNull('done_at')
            ->get()
            ->keyBy('code');

        $customerPlaceholders = [];
        foreach ($customer_status as $code => $desc) {
            $isDone = $existingStatuses->has($code);
            $updatedAt = $isDone
                ? $existingStatuses[$code]->done_at->format('d M Y, h:i A')
                : 'Pending';

            $icon = $isDone
                ? '<svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 text-green-600"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" 
                        class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" 
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z" />
                    </svg>';

            $customerPlaceholders[] = Grid::make(2)->schema([
                Placeholder::make("customer_desc_{$code}")
                    ->label(false)
                    ->content(new HtmlString(
                        '<div class="flex items-center space-x-2">'
                        . $icon .
                        '&nbsp;<span>' . e($desc) . '</span></div>'
                    )),
                Placeholder::make("customer_status_{$code}")
                    ->label(false)
                    ->content($updatedAt),
            ]);
        }

        // rider status
        $rider_codes = ['11', '12', '13', '14', '15', '16', '17'];
        $rider_status = $statuses
            ->whereIn('code', $rider_codes)
            ->sortBy('code')
            ->pluck('desc', 'code')
            ->toArray();

        $existingStatuses = OrderStatus::where('order_id', $this->record->id)
            ->whereIn('code', $rider_codes)
            ->where('is_done', true)
            ->whereNotNull('done_at')            
            ->get()
            ->keyBy('code');

        $riderPlaceholders = [];
        foreach ($rider_status as $code => $desc) {
            $isDone = $existingStatuses->has($code);
            $updatedAt = $isDone
                ? $existingStatuses[$code]->done_at->format('d M Y, h:i A')
                : 'Pending';

            $icon = $isDone
                ? '<svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 text-green-600"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" 
                        class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" 
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z" />
                    </svg>';

            $riderPlaceholders[] = Grid::make(2)->schema([
                Placeholder::make("rider_desc_{$code}")
                    ->label(false)
                    ->content(new HtmlString(
                        '<div class="flex items-center space-x-2">'
                        . $icon .
                        '&nbsp;<span>' . e($desc) . '</span></div>'
                    )),
                Placeholder::make("rider_status_{$code}")
                    ->label(false)
                    ->content($updatedAt),
            ]);
        }

        // merchant status
        $merchant_codes = ['21', '22', '23', '24', '25', '26'];
        $merchant_status = $statuses
            ->whereIn('code', $merchant_codes)
            ->sortBy('code')
            ->pluck('desc', 'code')
            ->toArray();

        $existingStatuses = OrderStatus::where('order_id', $this->record->id)
            ->whereIn('code', $merchant_codes)
            ->where('is_done', true)
            ->whereNotNull('done_at')            
            ->get()
            ->keyBy('code');

        $merchantPlaceholders = [];
        foreach ($merchant_status as $code => $desc) {
            $isDone = $existingStatuses->has($code);
            $updatedAt = $isDone
                ? $existingStatuses[$code]->done_at->format('d M Y, h:i A')
                : 'Pending';

            $icon = $isDone
                ? '<svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 text-green-600"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" 
                        class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" 
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z" />
                    </svg>';

            $merchantPlaceholders[] = Grid::make(2)->schema([
                Placeholder::make("merchant_desc_{$code}")
                    ->label(false)
                    ->content(new HtmlString(
                        '<div class="flex items-center space-x-2">'
                        . $icon .
                        '&nbsp;<span>' . e($desc) . '</span></div>'
                    )),
                Placeholder::make("merchant_status_{$code}")
                    ->label(false)
                    ->content($updatedAt),
            ]);
        }

        // addons
        $addons = $this->record->order_addons
            ->map(function ($orderAddon) {
                return [
                    'title' => $orderAddon->addon?->title,
                    'price' => $orderAddon->addon?->price,
                ];
            })
            ->filter(fn($item) => $item['title']) 
            ->values();

        $addonPlaceholders = $addons->map(function ($addon, $index) {
            return [
                Placeholder::make('addon_title_' . $index)
                    ->label(false)
                    ->content($addon['title']),

                Placeholder::make('addon_price_' . $index)
                    ->label(false)
                    ->content('RM ' . number_format($addon['price'], 2)),
            ];
        })->flatten(1)->toArray();

        $addonNames = $addons->pluck('title')->implode(', ');





        return $form->schema([
            Grid::make(3)
                ->schema([

                    // Left Column
                    Grid::make()
                        ->schema([
                            Section::make('Order Details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_customer_name')->label(false)->content('Customer'),
                                            Placeholder::make('customer_name')->label(false)->content($this->record->user->name ?? null),
                                            Placeholder::make('label_order_number')->label(false)->content('Order #'),
                                            Placeholder::make('order_number')->label(false)->content(new \Illuminate\Support\HtmlString('<strong>' . $this->record->id . '</strong> <span style="color:#6b7280;font-size:12px">(shown in customer/rider/merchant apps)</span>')),
                                            Placeholder::make('label_order_id')->label(false)->content('Order ID'),
                                            Placeholder::make('order_id')->label(false)->content($this->record->series_no ?? null),
                                            Placeholder::make('label_order_date')->label(false)->content('Order Date'),
                                            Placeholder::make('order_date')->label(false)->content($this->record->booking->pickup_date),
                                            Placeholder::make('label_bag_quantity')->label(false)->content('Bag Quantity'),
                                            Placeholder::make('bag_quantity')->label(false)->content($this->record->quantity ?? null),
                                            Placeholder::make('label_transaction_id')->label(false)->content('Transaction ID'),
                                            Placeholder::make('transaction_id')->label(false)->content('-'),
                                            Placeholder::make('label_add_ons')->label(false)->content('Add-ons'),
                                            Placeholder::make('add_ons')->label(false)->content($addonNames),
                                            Placeholder::make('label_location')->label(false)->content('Location'),
                                            Placeholder::make('location')
                                                ->label(false)
                                                ->content(
                                                    $this->record->booking?->pickup_location
                                                        ? implode(', ', array_filter([
                                                            $this->record->booking->pickup_location->unit_no,
                                                            $this->record->booking->pickup_location->floor,
                                                            $this->record->booking->pickup_location->block,
                                                            $this->record->booking->pickup_location->address_line_1,
                                                            $this->record->booking->pickup_location->address_line_2,
                                                            $this->record->booking->pickup_location->postcode,
                                                            $this->record->booking->pickup_location->city,
                                                            $this->record->booking->pickup_location->state?->name,
                                                            $this->record->booking->pickup_location->country?->name,
                                                        ]))
                                                        : '-'
                                                ),

                                        ]),
                                ]),
                            Section::make('Order Summary')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_washing_charge')->label(false)->content('Washing Charge'),
                                            Placeholder::make('washing_charge')->label(false)->content('RM ' . number_format($this->record->sub_total, 2)),
                                            
                                            ...$addonPlaceholders,

                                            Placeholder::make('label_addon_discount')->label(false)->content('Add-ons Discount'),
                                            Placeholder::make('addon_discount')->label(false)->content('-RM ' . number_format($this->record->addon_discount, 2)),
                                            Placeholder::make('label_discount')->label(false)->content('Discount'),
                                            Placeholder::make('discount')->label(false)->content('RM ' . number_format($this->record->discount, 2)),

                                            Placeholder::make('label_birthday_reward')->label(false)->content('Birthday Reward'),
                                            Placeholder::make('birthday_reward')->label(false)->content('-RM ' . number_format($this->record->birthday_reward, 2)),
                                            Placeholder::make('label_insurance_fee')->label(false)->content('Risk-Free Insurance'),
                                            Placeholder::make('insurance_fee')->label(false)->content('RM ' . number_format($this->record->insurance_fee, 2)),

                                            Placeholder::make('label_delivery_charge')->label(false)->content('Delivery Charge'),
                                            Placeholder::make('delivery_charge')->label(false)->content('RM ' . number_format($this->record->shipping_cost, 2)),
                                            Placeholder::make('label_sst')->label(false)->content('SST (8%)'),
                                            Placeholder::make('sst')->label(false)->content('RM ' . number_format($this->record->tax_total, 2)),

                                            Placeholder::make('divider_total')
                                                ->label(false)
                                                ->columnSpanFull()
                                                ->content(new \Illuminate\Support\HtmlString('<hr style="border: 1px solid #f5f5f5;">')),

                                            Placeholder::make('label_total')->label(false)->content('Total'),
                                            Placeholder::make('total')->label(false)->content('RM ' . number_format($this->record->grand_total, 2)),
                                        ]),
                                ]),
                            Section::make('Laundry Bag Pickup Info')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Placeholder::make('label_bag_location_landmark')->label(false)->content('Bag Location and Landmark'),
                                            Placeholder::make('location_landmark')->label(false)->content($this->record->booking?->landmark),
                                            Placeholder::make('label_landmark_picture')->label(false)->content(''),                                                                                    
                                            View::make('image-preview')
                                                ->viewData([
                                                    'record' => $this->record,
                                                    'hasLandmarkPicture' => !empty(optional($this->record->booking)->landmark_picture),
                                                ]),
                                        ]),
                                ]),
                            Section::make('Customer Order Status')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            ...$customerPlaceholders,
                                        ]),
                                ]),
                            Section::make('Rider Order Status')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            ...$riderPlaceholders,
                                        ]),
                                ]),
                            Section::make('Merchant Order Status')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            ...$merchantPlaceholders,
                                        ]),
                                ]),

                        ])->columnSpan(2),

                    // Right Column
                    Grid::make()
                        ->schema([
                            Section::make('Assigned To')
                                ->schema([
                                    Select::make('rider_id')
                                        ->label('Rider')                                                                                 
                                        ->placeholder('Select Rider')
                                        ->searchable()
                                        ->options(function () {
                                            return \App\Models\User::role('rider')
                                                ->orderBy('name', 'asc')
                                                ->get()
                                                ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} — {$u->email}"]); // name + email so admin can tell riders with the same name apart
                                        })
                                        ->preload(function () {
                                            return \App\Models\User::role('rider')
                                                ->orderBy('name', 'asc')
                                                ->limit(10)
                                                ->get()
                                                ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} — {$u->email}"]);
                                        }),
                                    Select::make('merchant_id')
                                        ->label('Merchant')                                            
                                        ->placeholder('Select Merchant')
                                        ->searchable()
                                        ->preload(function () {
                                            return \App\Models\User::role('merchant')
                                                ->join('merchants', 'merchants.user_id', '=', 'users.id')
                                                ->orderBy('merchants.company_name', 'asc')
                                                ->limit(10)
                                                ->pluck('merchants.company_name', 'users.id');
                                        })
                                        ->options(function () {
                                            return User::query()
                                                ->role('merchant')
                                                ->join('merchants', 'merchants.user_id', '=', 'users.id')
                                                ->orderBy('merchants.company_name')
                                                ->pluck('merchants.company_name', 'users.id');
                                        }),
                                ]),

                            Section::make('Update Status')
                                ->schema([
                                    Select::make('admin_status')
                                        ->label('Admin Status')                                            
                                        ->placeholder('Select Status')
                                        ->searchable()
                                        ->preload()
                                        ->options(fn () => Order::statusOptions())
                                        ->disabled(fn ($get) => $this->record?->admin_status === '07'),
                                    Select::make('customer_status')
                                        ->label('Customer')                                            
                                        ->placeholder('Select Status')
                                        ->searchable()
                                        ->disabled()
                                        ->preload()
                                        ->options(function () {
                                            return Status::orderBy('code')->whereIn('code', ['01', '02', '03', '04', '05'])->pluck('desc', 'code');
                                        }),
                                    Select::make('rider_status')
                                        ->label('Rider')                                            
                                        ->placeholder('Select Status')
                                        ->searchable()
                                        ->disabled()                                        
                                        ->preload()
                                        ->options(function () {
                                            return Status::orderBy('code')->whereIn('code', ['11', '12', '13', '14', '15', '16', '17'])->pluck('desc', 'code');
                                        }),
                                    Select::make('merchant_status')
                                        ->label('Merchant')                                            
                                        ->placeholder('Select Status')
                                        ->searchable()
                                        ->disabled()                                        
                                        ->preload()
                                        ->options(function () {
                                            return Status::orderBy('code')->whereIn('code', ['21', '22', '23', '24', '25', '26'])->pluck('desc', 'code');
                                        }),
                                ]),
                        ])->columnSpan(1),

                ]),

        ]);
    }

}
