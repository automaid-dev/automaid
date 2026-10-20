<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateway\RevenueMonsterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Revenue Monster results. Nothing here trusts what the browser or the
 * notify body says: every success is confirmed by asking RM's API
 * (transaction query, customer lookup, or the token charge's own reply),
 * and every order is completed at most once (lock + "already paid" check).
 *
 *   GET|POST /webhook/rm/return/{order}        after the hosted payment page
 *   POST     /webhook/rm/notify                RM server-to-server notify
 *   GET      /webhook/rm/bind/{order}/{key}    after card binding (subscriptions)
 */
class RevenueMonsterController extends Controller
{
    public function __construct(protected RevenueMonsterService $rm)
    {
    }

    /** Customer comes back from RM's payment page. */
    public function getReturn(Request $request, $orderId)
    {
        ignore_user_abort(true);
        Log::info('RM return', ['order_id' => $orderId, 'query' => $request->query()]);

        $order = Order::find($orderId);
        $paid = $order && $this->confirmWebPayment($order, $request->query('orderId'));

        return view('payment.return', [
            'success' => $paid,
            'title' => $paid ? 'Payment successful' : 'Payment pending',
            'message' => $paid
                ? 'Your payment was received. You can return to the app.'
                : 'Your payment could not be verified yet. You can return to the app and check your order status.',
        ]);
    }

    /** RM notify (web payment or tokenized charge). Always answers 200. */
    public function notify(Request $request)
    {
        ignore_user_abort(true);
        $body = $request->json()->all() ?: $request->all();
        Log::info('RM notify', ['body' => $body]);

        $rmOrderId = $body['data']['order']['id'] ?? ($body['data']['orderId'] ?? null);
        $ourId = RevenueMonsterService::orderIdFromRm($rmOrderId)
            ?? (int) ($body['data']['order']['additionalData'] ?? 0);
        $order = $ourId ? Order::find($ourId) : null;

        if ($order && $order->status !== Order::PAID && $rmOrderId) {
            $this->confirmWebPayment($order, $rmOrderId);
        }
        return response()->json(['code' => 'SUCCESS']);
    }

    /** Customer comes back from binding a card for a subscription. */
    public function bindReturn(Request $request, $orderId, string $key)
    {
        ignore_user_abort(true);
        Log::info('RM bind return', ['order_id' => $orderId, 'query' => $request->query()]);

        $order = Order::find($orderId);
        if (!$order || !hash_equals(RevenueMonsterService::bindKey((int) $orderId), $key)) {
            abort(404);
        }

        $ok = $this->completeSubscription($order, (string) $request->query('status'), (string) $request->query('customerId'));

        return view('payment.return', [
            'success' => $ok,
            'title' => $ok ? 'Subscription active' : 'Subscription not completed',
            'message' => $ok
                ? 'Your card was saved and your first month is paid. You can return to the app.'
                : ($request->query('status') === 'CANCELLED'
                    ? 'Card setup was cancelled. You can return to the app and try again.'
                    : 'We could not complete your subscription. You can return to the app and check its status, or contact support.'),
        ]);
    }

    // ------------------------------------------------------------------

    /** Ask RM whether this order was paid; complete it if so. */
    protected function confirmWebPayment(Order $order, ?string $rmOrderId): bool
    {
        if ($order->status === Order::PAID) {
            return true;
        }
        // The RM order id we created at checkout is the reliable one; use
        // RM's own parameter only if it belongs to this order.
        if (!$rmOrderId || RevenueMonsterService::orderIdFromRm($rmOrderId) !== (int) $order->id) {
            $rmOrderId = $order->gateway_reference;
        }
        if (!$rmOrderId) {
            Log::info('RM: no Revenue Monster order id for this order', ['order_id' => $order->id]);
            return false;
        }

        return (bool) Cache::lock('rm-order-' . $order->id, 30)->block(20, function () use ($order, $rmOrderId) {
            $order->refresh();
            if ($order->status === Order::PAID) {
                return true;
            }
            try {
                $tx = $this->rm->findTransactionByOrderId($rmOrderId);
            } catch (\Throwable $e) {
                Log::error('RM transaction query failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                return false;
            }
            $status = strtoupper((string) ($tx['status'] ?? ''));
            $amount = (int) ($tx['order']['amount'] ?? -1);
            if ($status !== 'SUCCESS') {
                Log::info('RM transaction not successful', ['order_id' => $order->id, 'status' => $status]);
                return false;
            }
            if ($amount !== RevenueMonsterService::cents($order->grand_total)) {
                Log::error('RM paid amount differs from order total — not marking paid', ['order_id' => $order->id, 'paid_cents' => $amount, 'order_total' => $order->grand_total]);
                return false;
            }

            app(GkashController::class)->completePaidOrder($order, $tx, Payment::REVENUE_MONSTER, $tx['method'] ?? null);
            Log::info('RM order paid', ['order_id' => $order->id, 'transaction_id' => $tx['transactionId'] ?? null]);
            return true;
        });
    }

    /** Card bound → verify with RM → charge first month → activate. */
    protected function completeSubscription(Order $order, string $status, string $customerId): bool
    {
        if ($order->status === Order::PAID) {
            return true;
        }
        if ($status !== 'SUCCESS' || $customerId === '' || $order->order_type !== Order::SUBSCRIPTION) {
            if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
                $order->cancelUnpaidCheckout();
            }
            return false;
        }

        return (bool) Cache::lock('rm-order-' . $order->id, 60)->block(30, function () use ($order, $customerId) {
            $order->refresh();
            if ($order->status === Order::PAID) {
                return true;
            }

            try {
                $customer = $this->rm->getCustomer($customerId);
            } catch (\Throwable $e) {
                Log::error('RM customer lookup failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                return false;
            }
            $user = $order->user;
            $active = filter_var($customer['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$customer || !$active || ($user && strcasecmp((string) ($customer['email'] ?? ''), (string) ($order->billing_email ?: $user->email)) !== 0)) {
                Log::error('RM bound customer does not match this order', ['order_id' => $order->id, 'customer_id' => $customerId, 'customer' => $customer]);
                return false;
            }

            // A first charge already attempted for this order (e.g. page reloaded)?
            if (Cache::has('rm-first-charge-' . $order->id)) {
                Log::warning('RM first charge already attempted for this order; not charging again', ['order_id' => $order->id]);
                return false;
            }
            Cache::put('rm-first-charge-' . $order->id, true, now()->addDays(2));

            $charge = $this->rm->chargeCustomer(
                $customerId,
                (float) $order->grand_total,
                'AutoMaid subscription',
                'Subscription order #' . $order->id
            );
            if ($charge['outcome'] !== 'paid') {
                Log::warning('RM first subscription charge not successful', ['order_id' => $order->id, 'outcome' => $charge['outcome'], 'reason' => $charge['reason']]);
                if ($charge['outcome'] === 'failed') {
                    Cache::forget('rm-first-charge-' . $order->id);
                }
                return false;
            }

            $label = $customer['label'] ?? null; // e.g. "411111******1111"
            app(GkashController::class)->handleSubscriptionCheckout(
                $order,
                ['rm_customer' => $customer, 'rm_charge' => $charge['raw']],
                Payment::REVENUE_MONSTER,
                $customerId,
                'CARD'
            );
            if ($label && ($recurring = $order->subscription?->recurring_active)) {
                $recurring->cc_last_four = substr(preg_replace('/\D/', '', $label), -4);
                $recurring->save();
            }
            Log::info('RM subscription activated', ['order_id' => $order->id, 'customer_id' => $customerId]);
            return true;
        });
    }
}
