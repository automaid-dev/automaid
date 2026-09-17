<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Mail\NewOrderEmail;
use App\Mail\PurchaseBagEmail;
use App\Models\Activity;
use App\Models\AssignJob;
use App\Models\Bag;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\Qrcode;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherUser;
use App\Services\OneSignalService;
use App\Services\PaymentGateway\GkashPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Handles GKash payment confirmations for both one-off transactions
 * (Booking, Dry Cleaning, Bag Purchase) and — as of Phase 2 — the
 * initial subscription checkout that tokenizes a card for recurring
 * billing. Monthly recurring CHARGES themselves don't come through
 * this controller at all: per doc.gkash.my/v2/recurring-payments,
 * that's a synchronous server-to-server call
 * (GkashPaymentService::chargeRecurring), handled entirely inside
 * CheckNextPaymentSubscription.php, with no separate webhook.
 *
 * Built as its own self-contained controller rather than sharing code
 * with FiuuController, so nothing about the already-working Fiuu flow
 * needed to change to build this.
 */
class GkashController extends Controller
{
    public $gkash;

    public function __construct()
    {
        $this->gkash = new GkashPaymentService();
    }

    /**
     * Serves back the self-submitting HTML form GKash returns for
     * some checkouts instead of a plain redirect URL — see
     * GkashPaymentService::createPaymentUrl's docblock for why this
     * exists. Cached for 30 minutes at creation time; if a customer
     * takes longer than that to open the payment link, they'd need to
     * restart checkout.
     */
    public function getCheckoutForm(Request $request, $orderid)
    {
        $html = \Illuminate\Support\Facades\Cache::get('gkash_checkout_form_' . $orderid);
        if (!$html) {
            abort(404, 'This payment link has expired — please restart checkout.');
        }
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Customer-facing redirect after completing (or abandoning)
     * payment on GKash's hosted page. Deliberately does NOT mark
     * anything paid here — only getNotification (the server-to-server
     * callback) is treated as authoritative, so a customer closing the
     * tab early or GKash's redirect never itself grants a paid state.
     * Renders the same payment.return view Fiuu's own getReturn uses,
     * for a consistent "you can return to the app" webview regardless
     * of which gateway processed the payment.
     */
    public function getReturn(Request $request)
    {
        Log::info('GKash webhook hit: getReturn', ['ip' => $request->ip(), 'body' => $request->getContent()]);

        $content = $request->getContent();
        parse_str($content, $data);

        // Field name confirmed from doc.gkash.my/v2/recurring-payments'
        // callback example — it's `cartid`, not `ref_no` (Phase 1's
        // guess, since the exact callback shape wasn't confirmed yet
        // at that point).
        $order = Order::find($data['cartid'] ?? null);
        $paid = $order && $order->status === Order::PAID;

        return view('payment.return', [
            'success' => $paid,
            'title' => $paid ? 'Payment successful' : 'Payment pending',
            'message' => $paid
                ? 'Your payment was received. You can return to the app.'
                : 'Your payment could not be verified yet. You can return to the app and check your order status.',
        ]);
    }

    /**
     * Server-to-server payment confirmation — the actual source of
     * truth. Independently verifies the signature before trusting
     * anything in the payload (see GkashPaymentService::verifyCallback
     * and its caveat on the callback signature formula specifically).
     *
     * Per doc.gkash.my/v2/recurring-payments: "Verify the callback
     * signature and print 'OK' in the response body" — GKash expects
     * the literal text OK back, not JSON or an empty 200.
     */
    public function getNotification(Request $request)
    {
        Log::info('GKash webhook hit: getNotification', ['ip' => $request->ip(), 'body' => $request->getContent()]);

        $content = $request->getContent();
        parse_str($content, $data);

        $checkPayment = $this->gkash->verifyCallback($data);

        if (!$checkPayment) {
            Log::warning('GKash webhook signature verification failed', ['data' => $data]);
            return response('signature verification failed', 400);
        }

        $order = Order::find($data['cartid'] ?? null);
        Log::info('GKash payment check', [
            'cartid_requested' => $data['cartid'] ?? null,
            'order_found' => $order ? true : false,
            'order_type' => $order->order_type ?? null,
            'order_status' => $order->status ?? null,
            'gkash_status' => $data['status'] ?? null,
        ]);

        if (!$order) {
            return response('OK', 200);
        }

        // "88 - Transferred" is the one confirmed success example from
        // GKash's docs — see the matching comment on
        // GkashPaymentService::chargeRecurring for why only this exact
        // pattern is treated as success rather than guessing at the
        // full status code table.
        $isSuccess = str_starts_with((string) ($data['status'] ?? ''), '88');
        if (!$isSuccess) {
            Log::info('GKash payment not successful, leaving order as-is', ['status' => $data['status'] ?? null]);
            return response('OK', 200);
        }

        // Phase 2: initial subscription checkout — tokenizes the card
        // via the epkey GKash includes in this callback, then hands
        // off to the same activation shape Fiuu's subscription flow
        // uses (Subscription::ACTIVE, a PaymentRecurring row storing
        // the token for the monthly job to use).
        if ($order->order_type == Order::SUBSCRIPTION) {
            return $this->handleSubscriptionCheckout($order, $data);
        }

        if (in_array($order->order_type, [
            Order::SUBSCRIPTION_RENEWAL,
            Order::SUBSCRIPTION_UPDATE,
            Order::SUBSCRIPTION_UPGRADE,
        ])) {
            // Renewals never reach here (synchronous, no webhook — see
            // class docblock). Update/upgrade checkouts for an
            // already-GKash subscription would land here in principle,
            // but re-tokenizing mid-lifecycle isn't handled yet.
            Log::error('GKash webhook received a subscription update/upgrade order — not yet supported.', ['order_id' => $order->id]);
            return response('OK', 200);
        }

        $payment = $order->payment;
        $bag = $order->bag;
        $order_booking = $order->order_booking;

        // insert transaction
        $transaction = Transaction::firstOrCreate(
            ['order_id' => $order->id, 'payment_id' => $payment->id],
            [
                'date' => now(),
                'type' => $order->order_type,
                'amount' => $order->grand_total,
                'status' => Transaction::PAID,
            ]
        );

        // update payment status
        $payment->data = json_encode($data);
        $payment->payment_method = Payment::GKASH;
        $payment->status = Payment::PAID;
        // Field name confirmed from the docs' callback example —
        // `PaymentType` (capital P and T), not `payment_type`.
        $payment->channel = $data['PaymentType'] ?? null;
        $payment->is_paid = true;
        $payment->paid_at = now();
        $payment->save();

        // update order status
        $order->status = Order::PAID;
        $order->save();

        // order type purchase bag
        if ($order->order_type == Order::PURCHASE_BAG) {

            if ($bag) {
                $bag->status_payment = Bag::PAID;
                $bag->save();
            }

            // auto insert qrcodes
            if ($order->quantity > 0) {
                for ($i = 0; $i < $order->quantity; $i++) {
                    $qr = new Qrcode();
                    $code = $qr->getNextSeriesNo();
                    Qrcode::create([
                        'series_no' => $code,
                        'user_id' => $order->user_id,
                        'status' => Qrcode::SCANNED,
                        'type' => Qrcode::AUTO,
                        'scan_at' => now(),
                        'scan_by' => $order->user_id,
                        'created_by' => $order->user_id,
                    ]);
                }
            }

            // insert activity
            Activity::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'transaction_id' => $transaction->id,
                    'user_type' => 'customer',
                    'title' => 'Purchase Bag',
                    'status' => Activity::ACTIVE,
                ],
            );

            // send email + in-app notification + push (purchase bag)
            $user = $order->user;
            $subject = 'Auto Maid: Invoice for your purchase';
            $emailContent = (new PurchaseBagEmail($user->name, $subject, $order))->render();
            (new OneSignalService())->notifyUser(
                $user,
                \App\Models\CustomerNotification::BAG_PURCHASED,
                $subject,
                'Your bag purchase is confirmed — thanks for using Auto Maid.',
                $emailContent,
                $order->id,
            );
        }

        // order type booking (Wash & Fold or Dry Cleaning — both share
        // this same order type, distinguished by service_category_id)
        else if ($order->order_type == Order::BOOKING) {

            $booking = Booking::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'pickup_location_id' => $order_booking->pickup_location_id,
                    'pickup_date' => $order_booking->pickup_date,
                ],
                [
                    'pickup_start_time' => $order_booking->pickup_start_time,
                    'pickup_end_time' => $order_booking->pickup_end_time,
                    'pickup_bag_quantity' => $order_booking->pickup_bag_quantity,
                    'is_folding' => $order_booking->is_folding,
                    'pickup_photo_path' => $order_booking->pickup_photo_path,
                    'pickup_note' => $order_booking->pickup_note,
                    'washing_charge' => $order_booking->washing_charge,
                    'addon_charge' => $order_booking->addon_charge,
                    'discount' => $order_booking->discount,
                    'delivery_charge' => $order_booking->delivery_charge,
                    'tax' => $order_booking->tax,
                    'grand_total' => $order_booking->grand_total,
                    'status' => Booking::ACTIVE,
                    'service_category_id' => $order_booking->service_category_id,
                    'items' => $order_booking->items,
                    'dry_clean_discount_percent' => $order_booking->dry_clean_discount_percent,
                ]
            );

            $order->booking_id = $booking->id;
            $order->save();

            // Count this booking against the subscription's plan order
            // quota for this cycle, if the customer is subscribed —
            // same reasoning as the matching block in
            // FiuuController::getNotification. A subscriber's booking
            // payment can go through either gateway even though their
            // subscription itself stays on whichever gateway it was
            // locked to — those are independent transactions.
            $subscription = $order->user->subscribe;
            if ($subscription) {
                $subscription->orders_used_current_cycle = $subscription->orders_used_current_cycle + 1;
                $subscription->save();

                $order->used_subscription_quota = true;
                $order->save();
            }

            // check voucher — recorded unconditionally, same reasoning
            // as the matching fix in FiuuController::getNotification:
            // eligibility was already enforced once, correctly, at
            // booking-creation time, and the previous `$taken &&
            // $taken < usage_limit` gate silently never recorded a
            // voucher's very first use (falsy 0) or any use of an
            // unlimited (null usage_limit) voucher.
            if ($order->voucher_code) {
                $voucher = Voucher::where('code', $order->voucher_code)->active()->first();
                if ($voucher) {
                    VoucherUser::firstOrCreate(
                        [
                            'voucher_id' => $voucher->id,
                            'user_id' => $order->user_id,
                            'order_id' => $order->id,
                        ],
                        [
                            'discount_amount' => $order->discount,
                        ]
                    );
                }
            }

            // insert order status
            $codes = [OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP, OrderStatus::RIDER_PENDING_FOR_ACCEPTANCE, OrderStatus::MERCHANT_PENDING_FOR_ACCEPTANCE];
            foreach ($codes as $code) {
                $is_customer = ($code == OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP) ? 1 : 0;
                $status = OrderStatus::firstOrCreate(
                    ['order_id' => $order->id, 'code' => $code]
                );

                if ($is_customer) {
                    AssignJob::firstOrCreate([
                        'order_id' => $order->id,
                        'code' => OrderStatus::CUSTOMER_WAITING_RIDER_FOR_PICKUP,
                        'user_id' => $order->user_id,
                        'order_status_id' => $status->id,
                    ]);
                }
            }

            // insert activity
            Activity::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'transaction_id' => $transaction->id,
                    'user_type' => 'customer',
                    'title' => 'Booking',
                    'status' => Activity::ACTIVE,
                ],
            );

            $user = $order->user;
            $subject = 'Auto Maid: Invoice for your order';
            $emailContent = (new NewOrderEmail($user->name, $subject, $order))->render();
            (new OneSignalService())->notifyUser(
                $user,
                \App\Models\CustomerNotification::NEW_BOOKING,
                $subject,
                'Your booking is confirmed — we\'ll keep you posted.',
                $emailContent,
                $order->id,
            );

            // assign order to rider & merchant — same command Fiuu
            // bookings trigger, gateway-agnostic.
            Artisan::call('automaid:assign-order-to-rider-and-merchant');
        }

        return response('OK', 200);
    }

    /**
     * Phase 2: activates a subscription whose initial checkout (card
     * tokenization) just succeeded via GKash. Mirrors the shape of
     * FiuuController's subscription activation — same end state
     * (Subscription::ACTIVE, a PaymentRecurring row the monthly job
     * reads from) — but stores GKash's epkey instead of Fiuu's token,
     * since CheckNextPaymentSubscription.php branches on
     * subscription.payment_gateway to know which shape to expect.
     *
     * @param  Order $order
     * @param  array<string, mixed> $data
     * @return \Illuminate\Http\Response
     */
    protected function handleSubscriptionCheckout(Order $order, array $data)
    {
        $payment = $order->payment;
        $subscription = $order->subscription;

        if (!$payment || !$subscription) {
            Log::error('GKash subscription callback: missing payment or subscription record.', ['order_id' => $order->id]);
            return response('OK', 200);
        }

        $transaction = \App\Models\Transaction::firstOrCreate(
            ['order_id' => $order->id, 'payment_id' => $payment->id],
            [
                'date' => now(),
                'type' => $order->order_type,
                'amount' => $order->grand_total,
                'status' => \App\Models\Transaction::PAID,
            ]
        );

        $payment->data = json_encode($data);
        $payment->payment_method = Payment::GKASH;
        $payment->status = Payment::PAID;
        $payment->channel = $data['PaymentType'] ?? null;
        $payment->is_paid = true;
        $payment->paid_at = now();
        $payment->save();

        $order->status = Order::PAID;
        $order->save();

        $subscription->status = \App\Models\Subscription::ACTIVE;
        $subscription->save();

        // epkey is the token the monthly renewal job will use — see
        // GkashPaymentService::chargeRecurring's docblock: this
        // rotates on every use, so this initial value is only ever
        // used for the FIRST renewal charge.
        $epkey = $data['epkey'] ?? null;
        if ($epkey) {
            \App\Models\PaymentRecurring::firstOrCreate(
                ['payment_id' => $payment->id, 'subscription_id' => $subscription->id],
                [
                    'token' => $epkey,
                    'payment_date' => now()->toDateString(),
                    'next_payment_date' => $subscription->renew_at,
                    'status' => \App\Models\PaymentRecurring::SUBSCRIPTION,
                    'status_payment' => \App\Models\PaymentRecurring::PAID,
                    'data' => json_encode($data),
                    'amount' => $order->grand_total,
                    'is_paid' => true,
                    'paid_at' => now(),
                ]
            );
        } else {
            Log::warning('GKash subscription checkout succeeded but no epkey was returned — recurring billing cannot be set up for this subscription.', ['order_id' => $order->id]);
        }

        Activity::firstOrCreate(
            [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'transaction_id' => $transaction->id,
                'user_type' => 'customer',
                'title' => 'Subscription',
                'status' => Activity::ACTIVE,
            ],
        );

        $user = $order->user;
        $subject = 'Auto Maid: Your subscription is active';
        $emailContent = (new \App\Mail\PurchaseSubscriptionEmail($user->name, $subject, $order))->render();
        (new OneSignalService())->notifyUser(
            $user,
            \App\Models\CustomerNotification::SUBSCRIPTION_CREATED,
            $subject,
            'Your subscription is now active — enjoy your benefits!',
            $emailContent,
            $order->id,
        );

        return response('OK', 200);
    }
}
