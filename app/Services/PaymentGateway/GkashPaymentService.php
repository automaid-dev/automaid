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
        $this->baseUrl = config('services.gkash.base_url')
            ?? ($this->environment === 'live'
                ? 'https://api.gkash.my'
                : 'https://api-staging.pay.asia');
        $this->merchantId = config('services.gkash.merchant_id');
        $this->signatureKey = config('services.gkash.signature_key');
    }

    /**
     * Builds the hosted checkout URL — POST /api/payment/form. Also
     * used to start a recurring subscription checkout by passing
     * recurringtype, per the confirmed "Web Payments" and
     * "Recurring payments" docs (the recurring flow is explicitly the
     * same request shape plus that one extra parameter).
     *
     * GKash returns one of two response shapes, not a flat URL:
     *   - redirect.url  -> a URL we can send the customer straight to
     *   - redirect.html -> a self-submitting HTML form we must render
     *     in the customer's browser ourselves
     * Since PaymentGatewayInterface promises callers a plain URL
     * string either way, the html case is handled by caching that
     * HTML and returning a URL to our own tiny passthrough route
     * (GkashController::getCheckoutForm) that serves it back verbatim.
     *
     * @param  array{orderid: string, amount: float|string, bill_name: string, bill_email: string, bill_mobile: string, bill_desc?: string, currency?: string, recurringtype?: string} $data
     * @return string
     */
    public function createPaymentUrl(array $data): string
    {
        $cartId = (string) $data['orderid'];
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $currency = $data['currency'] ?? 'MYR';

        // v_firstname/v_lastname per the confirmed parameter table —
        // there's no single "full name" field, so bill_name is split
        // on the first space. Not marked as strictly required in what
        // was shared (the doc's own minimal working example omits all
        // billing fields entirely), but sent anyway since more
        // complete buyer info can only help with any fraud/risk
        // checks on GKash's side.
        $nameParts = explode(' ', trim($data['bill_name'] ?? ''), 2);

        $payload = [
            'version' => '1.5.5',
            'CID' => $this->merchantId,
            'v_currency' => $currency,
            'v_amount' => $amount,
            'v_cartid' => $cartId,
            'v_firstname' => $nameParts[0] ?? '',
            'v_lastname' => $nameParts[1] ?? '',
            'v_billemail' => $data['bill_email'] ?? '',
            'v_billphone' => $data['bill_mobile'] ?? '',
            'v_productdesc' => $data['bill_desc'] ?? 'Automaid Payment',
            'returnurl' => route('webhook.gkash.return'),
            'callbackurl' => route('webhook.gkash.notification'),
        ];
        if (!empty($data['recurringtype'])) {
            $payload['recurringtype'] = $data['recurringtype'];
        }
        $payload['signature'] = $this->buildRequestSignature($cartId, $amount, $currency);

        // Logged unconditionally (not just on failure) — specifically
        // to make returnurl/callbackurl visible for diagnosing "GKash
        // never calls back at all" reports. These are built from
        // route(), which depends entirely on APP_URL in .env — if
        // that's not the real public domain, GKash's servers can
        // never reach either URL, which looks exactly like this from
        // the customer's side (stuck on some generic page, nothing
        // ever recorded here).
        \Log::info('GkashPaymentService::createPaymentUrl request', [
            'cart_id' => $cartId,
            'returnurl' => $payload['returnurl'],
            'callbackurl' => $payload['callbackurl'],
        ]);

        $response = Http::asJson()->post($this->baseUrl . '/api/payment/form', $payload);
        $result = $response->json();

        if (!$response->successful() || empty($result['redirect'])) {
            \Log::error('GkashPaymentService::createPaymentUrl failed', [
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => $result,
            ]);
            throw new \Exception('Could not create GKash payment: ' . ($result['message'] ?? $result['status'] ?? 'unknown error'));
        }

        if (!empty($result['redirect']['url'])) {
            \Log::info('GkashPaymentService::createPaymentUrl succeeded (redirect.url)', [
                'cart_id' => $cartId,
                'redirect_url' => $result['redirect']['url'],
            ]);
            return $result['redirect']['url'];
        }

        if (!empty($result['redirect']['html'])) {
            \Log::info('GkashPaymentService::createPaymentUrl succeeded (redirect.html)', ['cart_id' => $cartId]);
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
     * back directly in this response, no separate webhook involved.
     *
     * GKash returns a NEW epkey with every successful charge — the
     * token rotates, it is not reused indefinitely the way Fiuu's
     * recurring token is. Callers MUST store the returned epkey and
     * use THAT for the next cycle.
     *
     * @param  string $epkey
     * @param  float|string $amount
     * @param  string $cartId       must be unique per charge attempt
     * @param  string $recurringType
     * @return array{success: bool, epkey: ?string, status: ?string, description: ?string, raw: array}
     */
    public function chargeRecurring(string $epkey, $amount, string $cartId, string $recurringType): array
    {
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

        // "88 - Transferred" is a confirmed success status (the full
        // table now also confirms "66 - Failed" and "11 - Pending" as
        // the other possible values).
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
     * Independently verifies a GKash callback/return payload's
     * signature before trusting its claimed status — uses the
     * CONFIRMED callback-specific formula (see buildCallbackSignature),
     * which is a different field set than the request signature.
     *
     * @param  array<string, mixed> $data
     * @return bool
     */
    public function verifyCallback(array $data): bool
    {
        if (empty($data['signature']) || empty($data['cartid'])) {
            return false;
        }
        $expected = $this->buildCallbackSignature(
            $data['CID'] ?? $this->merchantId,
            $data['POID'] ?? '',
            (string) $data['cartid'],
            number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            $data['currency'] ?? 'MYR',
            (string) ($data['status'] ?? '')
        );
        return hash_equals($expected, (string) $data['signature']);
    }

    /**
     * Request signature (checkout + recurring charge), confirmed
     * directly from GKash's "Web Payments" parameter documentation:
     *
     *   1. Concatenate signatureKey;CID;v_cartid;v_amount;v_currency
     *      in that order, separated by semicolons.
     *   2. v_amount is formatted to 2 decimal places with the
     *      decimal point removed (100.00 -> 10000, 0.1 -> 010 —
     *      note the preserved leading zero, which is why this uses
     *      string formatting rather than integer arithmetic).
     *   3. Uppercase the ENTIRE resulting string — not just the cart
     *      ID. This was the actual bug in the previous version: the
     *      signature key itself (mixed case) was never being
     *      uppercased, so every signature sent was wrong regardless
     *      of any other field being correct.
     *   4. Hash with SHA512.
     *
     * @param  string $cartId   as originally given, NOT pre-uppercased (this method handles that)
     * @param  string $amount   decimal string, e.g. "100.00"
     * @param  string $currency
     * @return string
     */
    protected function buildRequestSignature(string $cartId, string $amount, string $currency): string
    {
        $amountFormatted = str_replace(['.', ','], '', number_format((float) $amount, 2, '.', ''));
        $string = "{$this->signatureKey};{$this->merchantId};{$cartId};{$amountFormatted};{$currency}";
        return hash('sha512', strtoupper($string));
    }

    /**
     * Callback/return signature — CONFIRMED to be a different field
     * set than the request signature (includes POID and status, which
     * the request signature does not):
     *
     *   signatureKey;CID;POID;v_cartid;v_amount;v_currency;status
     *
     * Same amount formatting and "uppercase the whole string" rule as
     * buildRequestSignature.
     *
     * @param  string $cid
     * @param  string $poid
     * @param  string $cartId
     * @param  string $amount
     * @param  string $currency
     * @param  string $status
     * @return string
     */
    protected function buildCallbackSignature(string $cid, string $poid, string $cartId, string $amount, string $currency, string $status): string
    {
        $amountFormatted = str_replace(['.', ','], '', number_format((float) $amount, 2, '.', ''));
        $string = "{$this->signatureKey};{$cid};{$poid};{$cartId};{$amountFormatted};{$currency};{$status}";
        return hash('sha512', strtoupper($string));
    }
}
