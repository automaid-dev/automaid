<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PaymentGateway\FiuuPaymentService;

class PaymentController extends Controller
{
    protected $fiuu;

    /**
     * [__construct description]
     * @param FiuuPaymentService $fiuu [description]
     */
    public function __construct(FiuuPaymentService $fiuu)
    {
        $this->fiuu = $fiuu;
    }

    /**
     * [pay description]
     * @return [type] [description]
     */
    public function pay()
    {
        $payment = $this->fiuu->createPayment([
            'amount' => 1000,
            'customer' => [
                'name' => 'Ali Raza',
                'email' => 'ali@example.com',
            ],
            'redirect_url' => route('api.webhook.fiuu.redirect'),
            'webhook_url' => route('api.webhook.fiuu.webhook'),
        ]);

        if (isset($payment['payment_url'])) {
            return redirect($payment['payment_url']);
        }
    }


}
