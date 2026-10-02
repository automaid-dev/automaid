<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Fiuu\Payment;
use App\Models\Order;
use App\Models\bag;
use App\Contracts\PaymentGatewayInterface;

class FiuuPaymentService implements PaymentGatewayInterface
{
    protected $baseUrl;
    protected $merchantId;
    protected $subMerchantId;
    protected $apiKey;
    protected $secret;

    protected $verifyKey;
    protected $secretKey;

    protected $recBaseUrl;
    protected $recMerchantId;
    protected $recVerifyKey;
    protected $recSecretKey;

    protected $environment;

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->baseUrl = config('services.fiuu.base_url');
        $this->merchantId = config('services.fiuu.merchant_id');
        $this->subMerchantId = config('services.fiuu.sub_merchant_id');
        $this->apiKey = config('services.fiuu.api_key');
        $this->secret = config('services.fiuu.secret');
        $this->environment = config('services.fiuu.environment');

        $this->verifyKey = config('services.fiuu.verify_key');
        $this->secretKey = config('services.fiuu.secret_key');

        $this->recBaseUrl = config('services.recurring.rec_base_url');
        $this->recMerchantId = config('services.recurring.rec_merchant_id');
        $this->recVerifyKey = config('services.recurring.rec_verify_key');
        $this->recSecretKey = config('services.recurring.rec_secret_key');
    }

    /**
     * The Fiuu account used for subscriptions (sign-up card payment,
     * card update, upgrade top-up AND the monthly token charge). A card
     * token can only be charged by the merchant ID that created it, so
     * everything subscription-related runs on the same account:
     * RECURRING_MERCHANT_ID / RECURRING_VERIFY_KEY / RECURRING_SECRET_KEY.
     * Falls back to the normal account if those aren't configured.
     */
    public static function recurring(): self
    {
        $instance = new self();
        return $instance->forMerchant($instance->recMerchantId);
    }

    /**
     * Same service, switched to whichever of our two Fiuu accounts owns
     * $merchantId (the `domain` Fiuu sends back, or the merchant ID a
     * stored token was created under). Unknown/empty -> normal account.
     */
    public function forMerchant(?string $merchantId): self
    {
        $clone = clone $this;
        if ($merchantId && $this->recMerchantId && $merchantId === $this->recMerchantId
            && $this->recVerifyKey && $this->recSecretKey) {
            $clone->merchantId = $this->recMerchantId;
            $clone->subMerchantId = null;
            $clone->verifyKey = $this->recVerifyKey;
            $clone->secretKey = $this->recSecretKey;
        }
        return $clone;
    }

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    /**
     * Charge a stored card token (Fiuu Recurring API v7, RecordType "T",
     * merchant-initiated). Request format and checksum per Fiuu's
     * "Recurring API Specification v7.1.4":
     *   T|MerchantID|SubMerchant|Token|OrderID|Currency|Amount|Name|Email|Mobile|Desc|Checksum|CustomerId
     *   Checksum = md5(RecordType . MerchantID . SubMerchant . Token . OrderID . Currency . Amount . VerifyKey)
     *
     * IMPORTANT: an 'accepted' reply only means Fiuu accepted the
     * request. The actual result (00 paid / 11 failed / 22 pending) is
     * POSTed later to the merchant Callback URL — see
     * SubscriptionRenewalService::handleFiuuCallback(). Never treat
     * 'accepted' as paid.
     *
     * @return array{accepted: bool, tran_id: ?string, reason: ?string, raw: mixed}
     */
    public function chargeToken(string $token, string $orderRef, $amount, ?string $name, ?string $email, ?string $phone, ?string $customerId = null, string $description = 'AutoMaid Subscription'): array
    {
        $amount = number_format((float) $amount, 2, '.', '');
        $sub = '';
        $clean = fn ($v) => str_replace('|', ' ', (string) $v); // '|' is the field separator
        $checksum = md5('T' . $this->merchantId . $sub . $token . $orderRef . 'MYR' . $amount . $this->verifyKey);

        $dataString = implode('|', [
            'T', $this->merchantId, $sub, $token, $orderRef, 'MYR', $amount,
            $clean($name), $clean($email), $clean($phone), $clean($description),
            $checksum, $clean($customerId),
        ]);

        $url = rtrim($this->recBaseUrl ?: 'https://pay.fiuu.com', '/') . '/RMS/API/Recurring/input_v7.php';
        try {
            $resp = Http::asForm()->timeout(30)->post($url, ['0' => $dataString]);
            $json = $resp->json();
        } catch (\Throwable $e) {
            return ['accepted' => false, 'tran_id' => null, 'reason' => 'Could not reach Fiuu: ' . $e->getMessage(), 'raw' => null];
        }

        $first = is_array($json) ? ($json[0] ?? $json) : null;
        $accepted = is_array($first) && ($first['status'] ?? null) === 'accepted';

        return [
            'accepted' => $accepted,
            'tran_id' => is_array($first) ? (isset($first['tranID']) ? (string) $first['tranID'] : null) : null,
            'reason' => is_array($first) ? ($first['reason'] ?? null) : 'Unexpected response',
            'raw' => $json ?? $resp->body(),
        ];
    }

    /**
     * Verify the skey on a recurring-result callback. Fiuu's recurring
     * spec sample signs with the Verify Key, while normal payment
     * callbacks sign with the Secret Key — accept either (both are
     * private to us), using the keys of the account named in `domain`.
     */
    public function verifyRecurringCallback(array $data): bool
    {
        $svc = $this->forMerchant($data['domain'] ?? null);
        $key0 = md5(($data['tranID'] ?? '') . ($data['orderid'] ?? '') . ($data['status'] ?? '') . ($data['domain'] ?? '') . ($data['amount'] ?? '') . ($data['currency'] ?? ''));
        $base = ($data['paydate'] ?? '') . ($data['domain'] ?? '') . $key0 . ($data['appcode'] ?? '');
        $skey = (string) ($data['skey'] ?? '');
        return $skey !== ''
            && (hash_equals(md5($base . $svc->verifyKey), $skey) || hash_equals(md5($base . $svc->secretKey), $skey));
    }

    /**
     * [make description]
     * @param  [type] $baseUrl    [description]
     * @param  [type] $merchantId [description]
     * @param  [type] $apiKey     [description]
     * @param  [type] $secret     [description]
     * @param  [type] $verifyKey  [description]
     * @param  [type] $secretKey  [description]
     * @return [type]             [description]
     */
    public static function make($baseUrl = null, $merchantId = null, $subMerchantId = null, $apiKey = null, $secret = null, $verifyKey = null, $secretKey = null)
    {
        return new self($baseUrl, $merchantId, $subMerchantId, $apiKey, $secret, $verifyKey, $secretKey);
    }

    /**
     * [getPaymentUrl description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function getPaymentUrl(array $data)
    {
        $rms = new Payment($this->merchantId, $this->verifyKey, $this->secretKey, $this->environment);

        // THE FIX: returnUrl/callbackurl were never being passed to Fiuu
        // at all — every payment request left them null, so Fiuu had
        // nothing to redirect/notify to for that specific transaction
        // and fell back to whatever generic default (if any) is set in
        // the merchant dashboard. That's why the customer saw Fiuu's own
        // generic success page instead of landing back in the app, and
        // why the notification webhook never fired — there was nothing
        // wrong with the webhook handlers themselves, they were simply
        // never being called.
        //
        // route() builds these from the named routes in routes/web.php,
        // using APP_URL — make sure that's set to
        // https://app.automaid.asia in .env, or these will still point
        // at the wrong host.
        //
        // Only returnUrl and callbackurl are wired here — Fiuu's own
        // terminology treats callbackurl as serving both "callback" and
        // "notification" purposes (one URL, not two), which is what
        // FiuuController::getNotification is for. cancelurl (redirect on
        // an abandoned/cancelled payment) is left as Fiuu's own default
        // since there's no dedicated cancel handler in this codebase yet.
        // FiuuController::getCallback exists but isn't referenced
        // anywhere else in the codebase either — it duplicates
        // getNotification's logic almost exactly but was never actually
        // wired to anything before this fix, so it's left unmapped
        // rather than guessed into a slot (like cancelurl) it doesn't
        // semantically belong in.
        $paymentUrl = $rms->getPaymentUrl(
            $data['orderid'],
            $data['amount'],
            $data['bill_name'],
            $data['bill_email'],
            $data['bill_mobile'],
            $data['bill_desc'] ?? 'Fiuu Payment',
            $data['channel'] ?? null,
            $data['currency'] ?? 'MYR',
            route('webhook.fiuu.return'),
            route('webhook.fiuu.notification'),
        );
        return $paymentUrl;
    }

    /**
     * [getVcode description]
     * @param  [type] $amount  [description]
     * @param  [type] $orderid [description]
     * @return [type]          [description]
     */
    public function getVcode($amount, $orderid)
    {
        return md5($amount . $this->recMerchantId . $orderid . $this->recVerifyKey);
    }

    /**
     * [getCheckSum description]
     * @param  [type] $amount  [description]
     * @param  [type] $orderid [description]
     * @param  [type] $token   [description]
     * @return [type]          [description]
     */
    public function getCheckSum($amount, $orderid, $token)
    {
        return md5('T'. $this->merchantId . $token . $orderid . 'MYR' . $amount . $this->verifyKey);
    }

    /**
     * [getPaymentRequest description]
     * @param  array  $order [description] 
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public function getPaymentRequest($order, $token)
    {
        $checksum = $this->getCheckSum($order->grand_total, $order->id, $token);
        $dataString = 'T|' . $this->merchantId . '||' . $token . '|' . $order->id . '|MYR|' . $order->grand_total . '|' . $order->billing_name . '|' . $order->billing_email . '|' . $order->billing_phone . '|Subscription|' . $checksum . '|' . $order->user_id;

        $url = $this->baseUrl . '/RMS/API/Recurring/input_v7.php';
        $resp = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->withBody('0=' . urlencode($dataString), 'application/x-www-form-urlencoded')
        ->post($url);
        return $resp->json();
    }

    public function getPaymentRequest2($order, $token)
    {
        $checksum = $this->getCheckSum($order->grand_total, $order->id, $token);
        $dataString = 'T|' . $this->merchantId . '||' . $token . '|' . $order->id . '|MYR|' . $order->grand_total . '|' . $order->billing_name . '|' . $order->billing_email . '|' . $order->billing_phone . '|Subscription|' . $checksum . '|' . $order->user_id;
        return $dataString;
    }

    /**
     * [checkVerifySignature description]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function checkVerifySignature(array $data)
    {
        // Subscription payments run on the recurring account, so verify
        // with the keys of whichever account Fiuu says sent this.
        $svc = $this->forMerchant($data['domain'] ?? null);
        $rms = new Payment($svc->merchantId, $svc->verifyKey, $svc->secretKey, $this->environment);      
        $key = md5($data['tranID'] . $data['orderid'] . $data['status'] . $data['domain'] . $data['amount'] . $data['currency']);
        return $rms->verifySignature($data['paydate'], $data['domain'], $key, $data['appcode'], $data['skey']);
    }

    /**
     * PaymentGatewayInterface adapter — thin passthrough to the
     * existing getPaymentUrl(), kept as its own method (rather than
     * renaming getPaymentUrl itself) so every existing call site that
     * already calls getPaymentUrl() directly keeps working unchanged.
     */
    public function createPaymentUrl(array $data): string
    {
        return $this->getPaymentUrl($data);
    }

    /**
     * PaymentGatewayInterface adapter — thin passthrough to
     * checkVerifySignature(), same reasoning as createPaymentUrl()
     * above. FiuuController's webhook continues calling
     * checkVerifySignature() directly (Fiuu's own webhook payload
     * shape is specific to Fiuu, not something worth genericizing),
     * this exists for any future caller that only knows about the
     * shared PaymentGatewayInterface.
     */
    public function verifyCallback(array $data): bool
    {
        return (bool) $this->checkVerifySignature($data);
    }

    /**
     * [getEscrowService description]
     * @param  string $txnID [description]
     * @return [type]        [description]
     */
    public function getEscrowService(string $txnID)
    {
        $party = 'S';
        $tag = 'OK';
        $mesg = 'captured';
        $skey = md5($txnID . $this->merchantId . $party . $tag . $mesg . sha1($this->verifyKey));
        $payload = [
            'txnID'      => $txnID,
            'merchantID' => $this->merchantId,
            'skey'       => $skey,
            'party'      => $party,
            'tag'        => $tag,
            'mesg'       => $mesg,
        ];
        $response = Http::asForm()->post($this->baseUrl . '/RMS/API/escrow/index.php', $payload);
        if ($response->failed()) {
            throw new \Exception('Escrow API Error: ' . $response->body());
        }
        return $response->json();
    }

    /**
     * [getPayeeProfile description]
     * @param  array  $payeeData [description]
     * @return [type]            [description]
     */
        public function getPayeeProfile(array $payeeData)
        {
            // encode profile data
            $profileJson = json_encode($payeeData, JSON_UNESCAPED_UNICODE);

            // use a secret key known only to you for extra integrity
            $secretKey = "AUTOMAID_SECRET_KEY";

            // create the hash
            $profileHash = hash_hmac('sha256', $profileJson, $secretKey);

            // generate skey
            $skey = md5('new' . $this->merchantId . $profileJson . $profileHash . sha1($this->secretKey));

            $payload = [
                'operator' => $this->merchantId,
                'skey' => $skey,
                'func' => 'new',
                'profile' => $profileJson,
                'profile_hash' => $profileHash,
            ];
            $response = Http::asForm()->post($this->baseUrl . '/RMS/API/MassPayment/payee_profile.php', $payload);
            if ($response->failed()) {
                throw new \Exception('Escrow API Error: ' . $response->body());
            }
            return $response->json();
        }

    /**
     * [getPayeeStanding description]
     * @param  array  $payeeData [description]
     * @return [type]            [description]
     */
    public function getPayeeStanding(array $payeeData)
    {
        // Format amount (2 decimal places)
        $formattedAmount = number_format($payeeData['amount'], 2, '.', '');
        $currency = 'MYR';

        $skey = md5(
            $this->merchantId .
            $payeeData['payeeId'] .
            $formattedAmount .
            $currency .
            sha1($this->secretKey)
        );

        $payload = [
            'operator'     => $this->merchantId,
            'skey'         => $skey,
            'payeeID'      => $payeeData['payeeId'],
            'amount'       => $formattedAmount,
            'currency'     => $currency,
            'reference_id' => $payeeData['referenceId'],
            'notify_url'   => $payeeData['notifyUrl'],
        ];

        $response = Http::asForm()->post($this->baseUrl . '/RMS/API/MassPayment/SI_by_payee.php', $payload);
        if ($response->failed()) {
            throw new \Exception('FIUU Payout API Error: ' . $response->body());
        }
        return $response->json();
    }

    /**
     * [getDirectStanding description]
     * @param  array  $payeeData   [description]
     * @param  int    $amount      [description]
     * @param  string $referenceId [description]
     * @param  string $notifyUrl   [description]
     * @return [type]              [description]
     */
    public function getDirectStanding(array $payeeData, int $amount, string $referenceId, string $notifyUrl)
    {
        $formattedAmount = number_format($amount, 2, '.', '');
        $payeeJson = json_encode($payeeData, JSON_UNESCAPED_UNICODE);
        $currency = 'MYR';

        $skey = md5(
            $this->merchantId .
            $formattedAmount .
            $currency .
            $payeeJson .
            $referenceId . 
            $notifyUrl .
            sha1($this->secretKey)
        );

        $payload = [
            'operator'     => $this->merchantId,
            'skey'         => $skey,
            'amount'       => $formattedAmount,
            'currency'     => $currency,
            'payee'        => $payeeJson,
            'reference_id' => $referenceId,
            'notify_url'   => $notifyUrl,
        ];

        $response = Http::asForm()->post($this->baseUrl . '/RMS/API/MassPayment/direct_SI.php', $payload);
        if ($response->failed()) {
            throw new \Exception('FIUU Direct SI API Error: ' . $response->body());
        }
        return $response->json();
    }

    /**
     * [getRequeryPayoutStanding description]
     * @param  int    $amount      [description]
     * @param  string $referenceId [description]
     * @param  int    $massId      [description]
     * @return [type]              [description]
     */
    public function getRequeryPayoutStanding(int $amount, string $referenceId, int $massId)
    {
        $formattedAmount = number_format($amount, 2, '.', '');
        $currency = 'MYR';
        
        $skey = md5(
            $this->merchantId .
            $formattedAmount .
            $currency .
            $referenceId .
            $massId .
            sha1($this->verifyKey)
        );

        $payload = [
            'operator'     => $this->merchantId,
            'skey'         => $skey,
            'amount'       => $formattedAmount,
            'currency'     => $currency,
            'reference_id' => $referenceId,
            'mass_id'      => $massId,
        ];

        $response = Http::asForm()->post($this->baseUrl . '/RMS/API/MassPayment/requery_SI.php', $payload);
        if ($response->failed()) {
            throw new \Exception('FIUU Requery API Error: ' . $response->body());
        }
        return $response->json();
    }



}


