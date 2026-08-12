<?php

namespace App\Console\Commands;

use App\Mail\RenewalSubscriptionEmail;
use App\Models\Activity;
use App\Models\PaymentRecurring;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\OneSignalService;
use App\Services\PaymentGateway\FiuuPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckNextPaymentSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automaid:check-next-payment-subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get payment recurring
        //
        // IMPORTANT FIX: this used to compare `next_payment_date >= today`,
        // which is backwards for a "find renewals that are due" query — it
        // excluded anything already overdue, and once a subscription's
        // next_payment_date slipped into the past (e.g. one missed cron
        // run, or a gateway hiccup), it could never match this condition
        // again, permanently locking that subscription out of renewal
        // forever even though `status` stays 'active' in the database.
        // Renewal should trigger once the due date has arrived OR passed.
        $recurrings = PaymentRecurring::whereHas('subscription', function($query) {
            $query->where(['status' => Subscription::ACTIVE]);
        })
        ->whereDate('next_payment_date', '<=', Carbon::today())
        ->whereNotNull('paid_at')
        ->where([
            'is_paid' => true, 
            'status_payment' => PaymentRecurring::PAID,
        ])
        ->whereIn('status', [PaymentRecurring::SUBSCRIPTION, PaymentRecurring::SUBSCRIPTION_RENEWAL])
        ->get();

        // have payment recurring
        if (count($recurrings) > 0) {
            foreach ($recurrings as $recurring) {

                // get subscription info
                $subscription = $recurring->subscription;

                // get payment info
                $payment = $recurring->payment;
                if ($recurring->payment->data) {

                    // get order info
                    $order = $recurring->payment->order;

                    // get user info
                    $user = $order->user;

                    // get token
                    $token = $recurring->token;

                    // have token
                    if ($token) {

                        // call payment recurring
                        $rms = new FiuuPaymentService();
                        $data_rec = $rms->getPaymentRequest($order, $token);

                        // get return data
                        if ($data_rec[0]['status'] == 'accepted') {

                            // insert transaction
                            $transaction = Transaction::firstOrCreate(
                                [
                                    'order_id' => $order->id, 
                                    'payment_id' => $payment->id,
                                    'type' => Transaction::SUBSCRIPTION_RENEWAL,                                     
                                    'date' => Carbon::now()->toDateString(),
                                ],
                                [
                                    'amount' => $order->grand_total,                    
                                    'status' => Transaction::PAID, 
                                ]
                            );

                            // insert payment recurring
                            $next = Carbon::now()->addDay(); // Carbon::now()->addMonth()->toDateString()
                            $new_recurring = PaymentRecurring::firstOrCreate(
                                [
                                    'payment_id' => $recurring->payment_id,
                                    'subscription_id' => $subscription->id,
                                    'transaction_id' => $transaction->id,
                                ],
                                [
                                    'token' => $token,
                                    'payment_date' => Carbon::now()->toDateString(),
                                    'next_payment_date' => $next,
                                    'status' => PaymentRecurring::SUBSCRIPTION_RENEWAL,
                                    'status_payment' => PaymentRecurring::PAID,
                                    'data' => json_encode($data_rec),
                                    'amount' => $recurring->payment->amount,
                                    'is_paid' => true,
                                    'paid_at' => now(),
                                ]
                            );

                            // insert activity (status subscription renewal)
                            $activity = Activity::firstOrCreate(
                                [
                                    'order_id' => $order->id, 
                                    'user_id' => $order->user_id,
                                    'transaction_id' => $transaction->id, 
                                    'user_type' => 'customer',
                                    'title' => 'Subscription Renewal', 
                                    'status' => Activity::ACTIVE
                                ],
                            );

                            // update subscription renew_at
                            $subscription->end_date = $next;
                            $subscription->renew_at = $next;
                            // new billing cycle starts — reset plan order-quota usage
                            $subscription->orders_used_current_cycle = 0;
                            $subscription->save();

                            // update status payment recurring
                            $recurring->status_payment = PaymentRecurring::COMPLETE;
                            $recurring->save();

                            // send email renewal
                            $subject = 'Auto Maid: Your subscription renewal is successful';
                            $emailContent = (new RenewalSubscriptionEmail($user->name, $subject, $order))->render();
                            $onesignal = new OneSignalService();
                            $onesignal->sendEmail(
                                $user->email,
                                $subject,
                                $emailContent,
                            );                   
                        }
                    }
                }
            }
        }
    }
}
