<?php

namespace App\Support;

/**
 * WhatsApp click-to-chat helpers (wa.me links). No Meta API involved —
 * these just open the other person's chat in the user's own WhatsApp.
 */
class WhatsApp
{
    /**
     * Normalise a Malaysian number to the international digits-only
     * format wa.me expects: "012-345 6789" / "0123456789" / "+60 12..."
     * -> "60123456789". Returns null if it doesn't look like a phone
     * number at all.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);            // 0060... international prefix
        } elseif (str_starts_with($digits, '0')) {
            $digits = '6' . $digits;                 // 012... -> 6012...
        } elseif (str_starts_with($digits, '1') && strlen($digits) <= 10) {
            $digits = '60' . $digits;                // 12... (leading 0 dropped)
        }

        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : null;
    }

    /**
     * Full wa.me link with an optional pre-filled message.
     */
    public static function link(?string $raw, ?string $message = null): ?string
    {
        $number = self::normalize($raw);
        if (!$number) {
            return null;
        }

        return 'https://wa.me/' . $number . ($message ? '?text=' . rawurlencode($message) : '');
    }

    /**
     * The WhatsApp number of a user — mobile_no is the OTP-verified
     * number captured at registration; phone_no is the older field.
     */
    public static function numberOf($user): ?string
    {
        if (!$user) {
            return null;
        }

        return self::normalize($user->mobile_no ?? null) ?? self::normalize($user->phone_no ?? null);
    }
}
