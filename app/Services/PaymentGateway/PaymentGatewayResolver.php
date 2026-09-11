<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGatewayInterface;

class PaymentGatewayResolver
{
    const FIUU = 'fiuu';
    const GKASH = 'gkash';

    /**
     * @param  string|null $gatewayCode 'fiuu' or 'gkash' — falls back
     *                                   to Fiuu for null/unrecognized
     *                                   values (e.g. a legacy Setting
     *                                   row from before this column
     *                                   existed), so this never breaks
     *                                   an existing working flow by
     *                                   defaulting to something new.
     * @return PaymentGatewayInterface
     */
    public function resolve(?string $gatewayCode): PaymentGatewayInterface
    {
        return match ($gatewayCode) {
            self::GKASH => app(GkashPaymentService::class),
            default => app(FiuuPaymentService::class),
        };
    }
}
