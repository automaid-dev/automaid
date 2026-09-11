<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Build the hosted checkout URL to redirect the customer to.
     *
     * @param  array{orderid: string, amount: float|string, bill_name: string, bill_email: string, bill_mobile: string, bill_desc?: string, currency?: string} $data
     * @return string
     */
    public function createPaymentUrl(array $data): string;

    /**
     * Independently verify a webhook/callback payload's signature
     * against this gateway's own secret — never trust the payload's
     * claimed status without this passing first.
     *
     * @param  array<string, mixed> $data
     * @return bool
     */
    public function verifyCallback(array $data): bool;
}
