<?php

namespace App\Services;

use App\Models\User;
use App\Models\Rider;
use App\Models\Merchant;
use App\Models\Commission;
use App\Models\CommissionTransaction;
use App\Models\Setting;

class CommissionService
{


    /**
     * [__construct description]
     */
    public function __construct()
    {

    }

	/**
	 * [insertCommissionEwallet description]
	 * @param  [type] $amount   [description]
	 * @param  [type] $user_id  [description]
	 * @param  [type] $order_id [description]
	 * @return [type]           [description]
	 */
	public function insertCommissionEwallet($amount, $user_id, $order_id)
	{
	    // check commission
	    $commission = Commission::where('user_id', $user_id)->first();
	    if (!$commission) {
	        $commission = new Commission();
	        $commission->user_id = $user_id;
	        $commission->balance = $amount;
	        $commission->status = Commission::PENDING;
	        $commission->last_transaction_at = now();
	        $commission->save();
	    }

	    // add transaction
	    //
	    // 'amount'/'final_amount' must be THIS order's own commission
	    // ($amount, computed by getTotalCommission() below and passed
	    // into this function) — not $commission->balance, which is the
	    // user's *cumulative running total from every prior order*.
	    // Using balance here meant every transaction after a user's
	    // very first one recorded the wrong figure: the total earned
	    // *before* this order, instead of what this order itself
	    // actually earned. See the identical fix + full explanation on
	    // DeliveryController::insertCommissionEwallet, which had the
	    // same bug — this service duplicates that method rather than
	    // being called by it, so both copies needed the same fix.
	    CommissionTransaction::firstOrCreate(
	        [
	            'commission_id' => $commission->id, 
	            'order_id' => $order_id,
	        ],
	        [
	            'type' => CommissionTransaction::EARNED,
	            'amount' => $amount,
	            'final_amount' => $amount,
	            'status' => CommissionTransaction::PENDING,
	            'desc' => null,
	        ]
	    );

	    // update balance
	    //
	    // Previously summed 'amount' here, but admin's own commission
	    // edit action (ViewCommission's "edit" row action) corrects
	    // 'final_amount' instead — meaning any admin-corrected
	    // transaction would silently drift out of sync with this
	    // balance, which never reflected the correction. final_amount
	    // is always populated at creation (set to the same value as
	    // amount below) and is the one field meant to represent "what
	    // actually gets paid", so it's the right one to sum here too.
	    $total = $commission->transactions()->sum('final_amount'); 
	    $commission->balance = $total;
	    $commission->last_transaction_at = now();
	    $commission->save();        
	    return true;
	}

	/**
	 * [getTotalCommission description]
	 * @param  [type] $role  [description]
	 * @param  [type] $type  [description]
	 * @param  [type] $total [description]
	 * @return [type]        [description]
	 */
	public function getTotalCommission($role, $type, $total)
	{
	    $commission = 0;

	    // get setting
	    $setting = Setting::find(1);

	    // role rider
	    if ($role == User::RIDER) {

	        // get rider commission
	        $limitCommission = $setting->rider_commission; // 10
	        $minCommission = $setting->rider_minimum_commission; // 100

	        // gig worker
	        if ($type == Rider::TYPE_GIG_WORKER) {
	            $commissionRate = $setting->rider_gig_worker_commission; // 15%
	        }

	        // staff auto maid
	        else {
	            $commissionRate = $setting->rider_staff_automaid_commission;                
	        }
	    }

	    // role merchant
	    else {

	        // get merchant commission
	        $limitCommission = $setting->merchant_commission;
	        $minCommission = $setting->merchant_minimum_commission;

	        // outlet partner
	        if ($type == Merchant::TYPE_OUTLET_PARTNER) {
	            $commissionRate = $setting->merchant_outlet_partner_commission;
	        }

	        // auto maid outlet
	        else {
	            $commissionRate = $setting->merchant_automaid_outlet_commission;                
	        }
	    }

	    // calculate commission
	    //
	    // Previously this was `$total * ($commissionRate / $limitCommission)`
	    // — dividing the percentage RATE by the RM LIMIT, which is not
	    // a percentage calculation at all (e.g. a 15% rate against a
	    // RM10 limit computed as total * 1.5, effectively a 150%
	    // commission). The correct calculation is simply the rate as a
	    // percentage of the order total.
	    $commission = $total * ($commissionRate / 100);

	    // Floor — never below the configured minimum, regardless of
	    // how small the order total (or its percentage-based
	    // commission) is. This also covers the RM0-order case the
	    // admin setting's own helper text describes.
	    if ($total == 0 || $commission < $minCommission) {
	        $commission = $minCommission;
	    }

	    // Ceiling — never above the configured per-order limit. This
	    // check didn't exist at all before, so a high-value order
	    // could pay out an uncapped commission regardless of whatever
	    // limit was configured in Settings.
	    if ($commission > $limitCommission) {
	        $commission = $limitCommission;
	    }

	    return $commission;
	}
}

