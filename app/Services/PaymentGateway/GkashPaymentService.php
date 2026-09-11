<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Contracts\PaymentGatewayInterface;

class GkashPaymentService implements PaymentGatewayInterface
{
    protected $baseUrl;
    protected $merchantId;
    protected $signatureKey;
    protected $environment;

    public function __construct()
    {
        $this->environment = config('services.gkash.environment');
        // Confirmed via doc.gkash.my/v2/recurring-payments' own cURL
        // examples (api-staging.pay.asia) and the original merchant
        // welcome email's "Live request url" (api.gkash.my) — these
        // are genuinely two different hosts, not sandbox/live paths on
        // the same one. GKASH_BASE_URL in .env overrides both if set.
        $this->baseUrl = config('services.gkash.base_url')
            ?? ($this->environment === 'live'
                ? 'https://api.gkash.my'
                : 'https://api-staging.pay.asia');
        $this->merchantId = config('services.gkash.merchant_id');
        $this->signatureKey = config('services.gkash.signature_key');
    }

    /**
     * Builds the hosted checkout URL — Step 1 of GKash's flow (POST
     * /api/payment/form). Also used to start a recurring subscription
     * checkout by passing recurringtype, per doc.gkash.my/v2/recurring-payments
     * ("include the recurringtype parameter... same parameters as Web
     * Payments").
     *
     * GKash returns one of two response shapes, not a flat URL:
     *   - redirect.url  -> a URL we can send the customer straight to
     *   - redirect.html -> a self-submitting HTML form we must render
     *     in the customer's browser ourselves
     * Since PaymentGatewayInterface promises callers a plain URL
     * string either way, the html case is handled by caching that
     * HTML and returning a URL to our own tiny passthrough route
     * (GkashController::getCheckoutForm) that serves it back verbatim
     * — the customer's browser still ends up auto-submitting to GKash,
     * callers just never need to know which shape GKash chose.
     *
     * @param  array{orderid: string, amount: float|string, bill_name: string, bill_email: string, bill_mobile: string, bill_desc?: string, currency?: string, recurringtype?: string} $data
     * @return string
     */
    public function createPaymentUrl(array $data): string
    {
        $cartId = strtoupper((string) $data['orderid']);
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $currency = $data['currency'] ?? 'MYR';

        $payload = [
            'version' => '1.5.5',
            'CID' => $this->merchantId,
            'v_currency' => $currency,
            'v_amount' => $amount,
            'v_cartid' => $cartId,
            'returnurl' => route('webhook.gkash.return'),
            'callbackurl' => route('webhook.gkash.notification'),
        ];
        if (!empty($data['recurringtype'])) {
            $payload['recurringtype'] = $data['recurringtype'];
        }
        $payload['signature'] = $this->buildRequestSignature($cartId, $amount, $currency);

        $response = Http::asJson()->post($this->baseUrl . '/api/payment/form', $payload);
        $result = $response->json();

        if (!$response->successful() || empty($result['redirect'])) {
            \Log::error('GkashPaymentService::createPaymentUrl failed', [
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => $result,
            ]);
            throw new \Exception('Could not create GKash payment: ' . ($result['message'] ?? 'unknown error'));
        }

        if (!empty($result['redirect']['url'])) {
            return $result['redirect']['url'];
        }

        if (!empty($result['redirect']['html'])) {
            $cacheKey = 'gkash_checkout_form_' . $data['orderid'];
            Cache::put($cacheKey, $result['redirect']['html'], now()->addMinutes(30));
            return route('webhook.gkash.checkout-form', ['orderid' => $data['orderid']]);
        }

        throw new \Exception('GKash response had no redirect.url or redirect.html.');
    }

    /**
     * Step 4 of the recurring flow — charges a previously-tokenized
     * card using the epkey from either the initial checkout callback
     * or the previous recurring charge. Synchronous: the result comes
     * back directly in this response, no separate webhook involved
     * (unlike the initial checkout, which confirms via callbackurl).
     *
     * IMPORTANT: GKash returns a NEW epkey with every successful
     * charge (per the docs: "Use it to initiate the next recurring
     * payment") — the token rotates, it is not reused indefinitely
     * the way Fiuu's recurring token is. Callers MUST store the
     * returned epkey and use THAT for the next cycle, not the one
     * that was just spent.
     *
     * @param  string $epkey
     * @param  float|string $amount
     * @param  string $cartId       must be unique per charge attempt (not reused from a previous cycle)
     * @param  string $recurringType
     * @return array{success: bool, epkey: ?string, status: ?string, description: ?string, raw: array}
     */
    public function chargeRecurring(string $epkey, $amount, string $cartId, string $recurringType): array
    {
        $cartId = strtoupper($cartId);
        $amount = number_format((float) $amount, 2, '.', '');

        $payload = [
            'version' => '1.5.5',
            'CID' => $this->merchantId,
            'v_currency' => 'MYR',
            'v_amount' => $amount,
            'v_cartid' => $cartId,
            'recurringtype' => $recurringType,
            'epkey' => $epkey,
        ];
        $payload['signature'] = $this->buildRequestSignature($cartId, $amount, 'MYR');

        $response = Http::asForm()->post($this->baseUrl . '/api/payment/submit', $payload);
        $result = $response->json() ?? [];

        \Log::info('GkashPaymentService::chargeRecurring', [
            'cart_id' => $cartId,
            'response_status' => $response->status(),
            'response_body' => $result,
        ]);

        // "88 - Transferred" / "00 - Approved" is the one confirmed
        // success example from GKash's docs — the full status code
        // table (specifically every failure code) wasn't included in
        // what was shared, so treat anything not matching this exact
        // pattern as failed/needs-review rather than guessing at which
        // other codes might also mean success.
        $status = (string) ($result['status'] ?? '');
        $success = str_starts_with($status, '88');

        return [
            'success' => $success,
            'epkey' => $result['epkey'] ?? null,
            'status' => $result['status'] ?? null,
            'description' => $result['description'] ?? null,
            'raw' => $result,
        ];
    }

    /**
     * Independently verifies a GKash callback payload's signature
     * before trusting its claimed status.
     *
     * NOTE: doc.gkash.my/v2/recurring-payments confirms the REQUEST
     * signature formula precisely (see buildRequestSignature below)
     * but doesn't show the callback's own signature formula in what
     * was shared — only that a `signature` field is present. This
     * applies the same formula as a best-effort, consistent with how
     * every other field in the callback (CID, cartid, amount,
     * currency) exactly mirrors the request. Confirm this specifically
     * if callback verification ever fails unexpectedly on an otherwise
     * genuine payment.
     *
     * @param  array<string, mixed> $data
     * @return bool
     */
    public function verifyCallback(array $data): bool
    {
        if (empty($data['signature']) || empty($data['cartid'])) {
            return false;
        }
        $expected = $this->buildRequestSignature(
            strtoupper((string) $data['cartid']),
            number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            $data['currency'] ?? 'MYR'
        );
        return hash_equals($expected, (string) $data['signature']);
    }

    /**
     * SHA512(signatureKey;merchantId;CARTID;amount_in_cents;currency)
     * — confirmed directly from doc.gkash.my/v2/recurring-payments'
     * own worked example. Two details that are easy to get wrong and
     * were confirmed from that same example:
     *   - the cart ID is uppercased in the signature string even
     *     though it's sent lowercase/as-given in the request itself
     *   - amount is in CENTS (e.g. "100.00" -> 10000), not the decimal
     *     string used everywhere else in the payload — this matches
     *     the laraditz/gkash package's own changelog entry about
     *     fixing "a bug on amount on signature generation"
     *
     * @param  string $cartId    already uppercased by the caller
     * @param  string $amount    decimal string, e.g. "100.00"
     * @param  string $currency
     * @return string
     */
    protected function buildRequestSignature(string $cartId, string $amount, string $currency): string
    {
        $amountInCents = (int) round(((float) $amount) * 100);
        $string = "{$this->signatureKey};{$this->merchantId};{$cartId};{$amountInCents};{$currency}";
        return hash('sha512', $string);
    }
}
