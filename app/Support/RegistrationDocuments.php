<?php

namespace App\Support;

use App\Models\User;

/**
 * The verification photos riders and merchants upload at registration,
 * plus the standard reasons admin can tick when sending a registration
 * back for re-upload. Shared by the admin Reject action and the app's
 * resubmission API, so both always talk about the same documents.
 */
class RegistrationDocuments
{
    /** key => [label, expiry field on the rider/merchant row or null] */
    public const RIDER = [
        'ic_front' => ['ID / passport (front)', null],
        'ic_back' => ['ID (back)', null],
        'license_front' => ['Driving licence (front)', 'license_expiry'],
        'license_back' => ['Driving licence (back)', 'license_expiry'],
        'jpj_grant' => ['JPJ grant / road tax', 'jpj_grant_expiry'],
    ];

    public const MERCHANT = [
        'ic_front' => ['ID / passport (front)', null],
        'ic_back' => ['ID (back)', null],
        'ssm_cert' => ['SSM certificate', null],
    ];

    public const EXPIRY_LABELS = [
        'license_expiry' => 'Driving licence expiry date',
        'jpj_grant_expiry' => 'JPJ grant / road tax expiry date',
    ];

    /** Expiry dates must be at least this many days ahead. */
    public const MIN_DAYS_VALID = 14;

    public const ISSUES = [
        'blurry' => 'Photo is blurry or unreadable',
        'cropped' => 'Photo is cut off — the whole document must be visible',
        'glare' => 'Glare or shadow covers the details',
        'mismatch' => "Details don't match your registration (name / ID number / plate number)",
        'wrong_document' => 'Wrong document uploaded',
        'license_expired' => 'Driving licence has expired',
        'license_expiring' => 'Driving licence expires within 2 weeks',
        'grant_expired' => 'JPJ grant / road tax has expired',
        'grant_expiring' => 'JPJ grant / road tax expires within 2 weeks',
        'ssm_invalid' => 'SSM certificate is expired or invalid',
    ];

    public const RIDER_ISSUES = ['blurry', 'cropped', 'glare', 'mismatch', 'wrong_document', 'license_expired', 'license_expiring', 'grant_expired', 'grant_expiring'];
    public const MERCHANT_ISSUES = ['blurry', 'cropped', 'glare', 'mismatch', 'wrong_document', 'ssm_invalid'];

    public static function forUser(User $user): array
    {
        return $user->hasRole('merchant') ? self::MERCHANT : self::RIDER;
    }

    public static function documentOptions(User $user): array
    {
        return array_map(fn ($d) => $d[0], self::forUser($user));
    }

    public static function issueOptions(User $user): array
    {
        $keys = $user->hasRole('merchant') ? self::MERCHANT_ISSUES : self::RIDER_ISSUES;
        return array_intersect_key(self::ISSUES, array_flip($keys));
    }

    /** Plain-text summary used for users.rejected_reason, the email and the app. */
    public static function summary(User $user, array $details): string
    {
        $docs = self::forUser($user);
        $lines = [];
        $issues = array_values(array_filter(array_map(fn ($k) => self::ISSUES[$k] ?? null, $details['issues'] ?? [])));
        if ($issues) {
            $lines[] = 'Issues found: ' . implode('; ', $issues) . '.';
        }
        $requested = array_values(array_filter(array_map(fn ($k) => $docs[$k][0] ?? null, $details['documents'] ?? [])));
        if ($requested) {
            $lines[] = 'Please upload a new photo of: ' . implode(', ', $requested) . '.';
        }
        if (!empty($details['note'])) {
            $lines[] = trim($details['note']);
        }
        if (empty($details['can_resubmit'])) {
            $lines[] = 'This application cannot be resubmitted. Please contact support if you have questions.';
        }
        return implode("\n", $lines);
    }
}
