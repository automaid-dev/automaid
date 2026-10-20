<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Revenue Monster Open API (v3).
 *
 *  - Bookings, dry cleaning, bags: hosted web payment (POST /v3/payment/online).
 *    RM redirects to /webhook/rm/return and notifies /webhook/rm/notify; both
 *    confirm the result by querying RM (GET /v3/payment/transaction/order/{id}),
 *    so nothing is ever marked paid on the strength of a redirect or an
 *    unverified notify alone.
 *  - Subscriptions: tokenized payment. The customer binds a card
 *    (POST /v3/tokenized-payment → paymentUrl), RM redirects to
 *    /webhook/rm/bind/{order}, we charge the first month on the bound card
 *    (POST /v3/customer/{id}/order) and keep the customer ID as the token
 *    for monthly renewals (SubscriptionRenewalService::chargeRevenueMonster).
 *
 * Every API call: OAuth client-credentials access token (cached) + RSA-SHA256
 * request signature, matching RM's official PHP SDK.
 */
class RevenueMonsterService implements PaymentGatewayInterface
{
    protected string $env;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $storeId;

    public function __construct()
    {
        $c = config('services.revenuemonster');
        $this->env = ($c['environment'] ?? 'production') === 'sandbox' ? 'sandbox' : 'production';
        $this->clientId = $c['client_id'] ?? null;
        $this->clientSecret = $c['client_secret'] ?? null;
        $this->storeId = $c['store_id'] ?? null;
    }

    // ------------------------------------------------------------------
    // Checkout
    // ------------------------------------------------------------------

    public function createPaymentUrl(array $data): string
    {
        $order = Order::find($data['orderid'] ?? null);
        if (!$order) {
            throw new \Exception('Online payment is temporarily unavailable. Please try again in a few minutes or contact AutoMaid support.');
        }

        if ($order->order_type === Order::SUBSCRIPTION) {
            return $this->createCardBinding($order, $data);
        }
        if (in_array($order->order_type, [Order::SUBSCRIPTION_UPGRADE, Order::SUBSCRIPTION_UPDATE, Order::SUBSCRIPTION_RENEWAL], true)) {
            Log::warning('Revenue Monster: plan change / manual renewal checkout is not supported yet', ['order_id' => $order->id, 'type' => $order->order_type]);
            throw new \Exception('Changing plans is not available for this subscription yet. Please contact AutoMaid support.');
        }

        $rmOrderId = self::rmOrderId($order);
        // Remember it, so return/notify can query RM even without RM's params.
        $order->gateway_reference = $rmOrderId;
        $order->save();
        $methods = array_values(array_filter(array_map('trim', explode(',', (string) config('services.revenuemonster.methods')))));
        $payload = [
            'order' => [
                'id' => $rmOrderId,
                'title' => mb_substr((string) ($data['bill_desc'] ?? 'AutoMaid payment'), 0, 32),
                'detail' => 'AutoMaid order #' . $order->id,
                'additionalData' => (string) $order->id,
                'amount' => self::cents($data['amount'] ?? $order->grand_total),
                'currencyType' => 'MYR',
            ],
            'customer' => [
                'userId' => (string) ($order->user_id ?? ''),
                'email' => (string) ($data['bill_email'] ?? ''),
                'countryCode' => '60',
                'phoneNumber' => self::localPhone($data['bill_mobile'] ?? ''),
            ],
            'method' => $methods,
            'type' => 'WEB_PAYMENT',
            'storeId' => (string) $this->storeId,
            'redirectUrl' => route('webhook.rm.return', ['order' => $order->id]),
            'notifyUrl' => route('webhook.rm.notify'),
            'layoutVersion' => 'v3',
        ];

        $res = $this->call('post', 'v3/payment/online', $payload);
        $url = $res['item']['url'] ?? null;
        if (!$url) {
            Log::error('Revenue Monster web payment failed', ['order_id' => $order->id, 'response' => $res]);
            throw new \Exception('Online payment is temporarily unavailable. Please try again in a few minutes or contact AutoMaid support.');
        }
        Log::info('Revenue Monster web payment created', ['order_id' => $order->id, 'rm_order_id' => $rmOrderId, 'checkout_id' => $res['item']['checkoutId'] ?? null]);
        return $url;
    }

    /** Subscription sign-up: card binding page; the first charge happens on return. */
    protected function createCardBinding(Order $order, array $data): string
    {
        $user = $order->user;
        $payload = [
            'storeId' => (string) $this->storeId,
            'email' => (string) ($data['bill_email'] ?? $user?->email ?? ''),
            'name' => (string) ($data['bill_name'] ?? $user?->name ?? 'Customer'),
            'countryCode' => '60',
            'phoneNumber' => self::localPhone($data['bill_mobile'] ?? $user?->mobile_no ?? ''),
            'productName' => 'AutoMaid subscription',
            'productDescription' => mb_substr((string) ($data['bill_desc'] ?? 'Monthly laundry subscription'), 0, 100),
            // Tied to this order by an HMAC in the path (no query string —
            // RM appends its own ?status=&customerId=).
            'redirectUrl' => route('webhook.rm.bind', ['order' => $order->id, 'key' => self::bindKey($order->id)]),
            'notifyUrl' => route('webhook.rm.notify'),
        ];

        $res = $this->call('post', 'v3/tokenized-payment', $payload);
        $url = $res['item']['paymentUrl'] ?? null;
        if (!$url) {
            Log::error('Revenue Monster card binding failed', ['order_id' => $order->id, 'response' => $res]);
            throw new \Exception('Subscriptions are temporarily unavailable. Please try again later or contact AutoMaid support.');
        }
        Log::info('Revenue Monster card binding created', ['order_id' => $order->id, 'customer_id' => $res['item']['id'] ?? null]);
        return $url;
    }

    /** GKash/Fiuu-style interface method — RM results are confirmed by query instead. */
    public function verifyCallback(array $data): bool
    {
        return false;
    }

    // ------------------------------------------------------------------
    // Queries & token charges
    // ------------------------------------------------------------------

    /** Transaction for one of our RM order IDs, or null if RM has none. */
    public function findTransactionByOrderId(string $rmOrderId): ?array
    {
        $res = $this->call('get', 'v3/payment/transaction/order/' . rawurlencode($rmOrderId));
        return $res['item'] ?? null;
    }

    public function getCustomer(string $customerId): ?array
    {
        $res = $this->call('get', 'v3/customer/' . rawurlencode($customerId));
        return $res['item'] ?? null;
    }

    /**
     * Charge a bound card (tokenized customer).
     *
     * @return array{outcome: string, transaction_id: ?string, reason: ?string, raw: array}
     *   outcome: paid | failed | pending ("pending" = unknown result, don't charge again)
     */
    public function chargeCustomer(string $customerId, float $amount, string $title, string $description = ''): array
    {
        try {
            $res = $this->call('post', 'v3/customer/' . rawurlencode($customerId) . '/order', [
                'currency' => 'MYR',
                'amount' => self::cents($amount),
                'title' => mb_substr($title, 0, 32),
                'description' => mb_substr($description ?: $title, 0, 100),
            ], 45);
        } catch (\Throwable $e) {
            Log::error('Revenue Monster token charge: no reply', ['customer_id' => $customerId, 'error' => $e->getMessage()]);
            return ['outcome' => 'pending', 'transaction_id' => null, 'reason' => 'No reply from Revenue Monster: ' . $e->getMessage(), 'raw' => []];
        }

        $item = $res['item'] ?? [];
        $status = strtoupper((string) ($item['status'] ?? ''));
        $outcome = match (true) {
            $status === 'SUCCESS' => 'paid',
            in_array($status, ['FAILED', 'CANCELLED', 'REVERSED', 'FULL_REFUNDED'], true) => 'failed',
            !empty($res['error']) && empty($item) => 'failed', // request refused, nothing charged
            default => 'pending',
        };
        Log::info('Revenue Monster token charge', ['customer_id' => $customerId, 'status' => $status, 'outcome' => $outcome, 'response' => $res]);

        return [
            'outcome' => $outcome,
            'transaction_id' => $item['transactionId'] ?? null,
            'reason' => $outcome === 'paid' ? null : trim(($res['error']['code'] ?? $status) . ' ' . ($res['error']['message'] ?? ($item['error']['message'] ?? ''))),
            'raw' => $res,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public static function bindKey(int $orderId): string
    {
        return substr(hash_hmac('sha256', 'rm-bind:' . $orderId, (string) config('app.key')), 0, 32);
    }

    /** Unique per checkout; "AM<order>T<time>" maps back to our order. */
    public static function rmOrderId(Order $order): string
    {
        return 'AM' . $order->id . 'T' . now()->format('ymdHis');
    }

    public static function orderIdFromRm(?string $rmOrderId): ?int
    {
        return preg_match('/^AM(\d+)T\d+$/', (string) $rmOrderId, $m) ? (int) $m[1] : null;
    }

    public static function cents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** RM wants the number without the 60 country prefix. */
    public static function localPhone($phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '60')) {
            $digits = substr($digits, 2);
        }
        return ltrim($digits, '0') !== '' ? $digits : '0';
    }

    protected function host(string $usage): string
    {
        $domain = $usage === 'oauth' ? 'oauth.revenuemonster.my' : 'open.revenuemonster.my';
        return 'https://' . ($this->env === 'sandbox' ? 'sb-' : '') . $domain;
    }

    protected function accessToken(): string
    {
        $key = 'rm_access_token_' . $this->env . '_' . md5((string) $this->clientId);
        if ($token = Cache::get($key)) {
            return $token;
        }
        $res = Http::withHeaders(['Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)])
            ->asJson()->timeout(20)
            ->post($this->host('oauth') . '/v1/token', ['grantType' => 'client_credentials']);
        $body = $res->json() ?? [];
        if (empty($body['accessToken'])) {
            Log::error('Revenue Monster OAuth failed', ['status' => $res->status(), 'body' => $body]);
            throw new \Exception('Online payment is temporarily unavailable. Please try again in a few minutes or contact AutoMaid support.');
        }
        $ttl = max(60, ((int) ($body['expiresIn'] ?? 3600)) - 300);
        Cache::put($key, $body['accessToken'], $ttl);
        return $body['accessToken'];
    }

    /**
     * Signed API call. Returns the decoded JSON (may contain `error`).
     */
    public function call(string $method, string $path, ?array $payload = null, int $timeout = 25): array
    {
        $method = strtolower($method);
        $url = $this->host('api') . '/' . ltrim($path, '/');
        $nonce = Str::random(32);
        $timestamp = (string) time();

        $json = null;
        $data = '';
        if (!empty($payload)) {
            self::ksortRecursive($payload);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
            $data = base64_encode($json);
        }

        $parts = [];
        if (is_array($payload)) {
            $parts[] = 'data=' . $data;
        }
        $parts[] = 'method=' . $method;
        $parts[] = 'nonceStr=' . $nonce;
        $parts[] = 'requestUrl=' . $url;
        $parts[] = 'signType=sha256';
        $parts[] = 'timestamp=' . $timestamp;

        $signature = '';
        if (!openssl_sign(implode('&', $parts), $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Revenue Monster: could not sign the request (check RM_PRIVATE_KEY / RM_PRIVATE_KEY_PATH).');
        }

        $req = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'X-Signature' => 'sha256 ' . base64_encode($signature),
            'X-Nonce-Str' => $nonce,
            'X-Timestamp' => $timestamp,
            'Accept' => 'application/json',
        ])->timeout($timeout);

        $response = match ($method) {
            'get' => $req->get($url),
            'put' => $json !== null ? $req->withBody($json, 'application/json')->put($url) : $req->put($url),
            default => $json !== null ? $req->withBody($json, 'application/json')->post($url) : $req->post($url),
        };

        $body = $response->json() ?? [];
        if (!$response->successful() || !empty($body['error'])) {
            Log::warning('Revenue Monster API error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'body' => $body]);
        }
        return is_array($body) ? $body : [];
    }

    protected function privateKey()
    {
        $c = config('services.revenuemonster');
        $pem = !empty($c['private_key_path']) && is_readable($c['private_key_path'])
            ? file_get_contents($c['private_key_path'])
            : str_replace('\\n', "\n", (string) ($c['private_key'] ?? ''));
        $key = openssl_pkey_get_private($pem);
        if (!$key) {
            throw new \Exception('Revenue Monster private key missing or invalid (RM_PRIVATE_KEY_PATH).');
        }
        return $key;
    }

    protected static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$v) {
            if (is_array($v)) {
                self::ksortRecursive($v);
            }
        }
        ksort($array);
    }
}
