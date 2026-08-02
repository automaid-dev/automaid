<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Fiuu\Payment;
use App\Models\Order;
use App\Models\bag;

class FiuuPaymentService 
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
        $paymentUrl = $rms->getPaymentUrl($data['orderid'], $data['amount'], $data['bill_name'], $data['bill_email'], $data['bill_mobile']);
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
        $rms = new Payment($this->merchantId, $this->verifyKey, $this->secretKey, $this->environment);      
        $key = md5($data['tranID'] . $data['orderid'] . $data['status'] . $data['domain'] . $data['amount'] . $data['currency']);
        return $rms->verifySignature($data['paydate'], $data['domain'], $key, $data['appcode'], $data['skey']);
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


