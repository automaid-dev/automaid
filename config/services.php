<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sqids' => [
        'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-',
    ],

    'onewaysms' => [
        'api_endpoint' => env('ONEWAYSMS_API_ENDPOINT', 'https://gateway.onewaysms.com.my'),
        'api_username' => env('ONEWAYSMS_API_USERNAME', 'APISVPJ2EZKBM'),
        'api_password' => env('ONEWAYSMS_API_PASSWORD', 'APISVPJ2EZKBMSVPJ2'),
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN', '7995173992:AAEJ6DPS_yf4W-DkMbxkGeaTgrMs-_1ENuE'),
        'chat_id' => env('TELEGRAM_ID', '142020397'),
        'groups' => [
            'dev' => env('TELEGRAM_GROUP_DEV', '-4637568495'),
            'prod' => env('TELEGRAM_GROUP_PROD', ''),
        ],
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'api_key' => env('ONESIGNAL_REST_API_KEY'),
        'subdomain' => env('ONESIGNAL_EMAIL_SUBDOMAIN'),

        'customer' => [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'api_key' => env('ONESIGNAL_REST_API_KEY')
        ],
        'merchant' => [
            'app_id' => env('ONESIGNAL_MERCHANT_APP_ID'),
            'api_key' => env('ONESIGNAL_MERCHANT_REST_API_KEY')
        ]
    ],

    'fiuu' => [
        // 'base_url' => env('FIUU_BASE_URL', 'https://sandbox-payment.fiuu.com'),
        'base_url' => env('FIUU_BASE_URL', 'https://sandbox-portal.fiuu.com'),
        'merchant_id' => env('FIUU_MERCHANT_ID', 'SB_payandwash'),
        'verify_key' => env('FIUU_VERIFY_KEY', 'b1e09b3b0eac78420b766ed2d633479e'),
        'secret_key' => env('FIUU_SECRET_KEY', '6e686bdf9acd298cbd606385d2f1500d'),  
        'environment' => env('FIUU_ENVIRONMENT', 'sandbox'),
    ],

    'gkash' => [
        // Live API host per the merchant onboarding email. GKash's demo
        // credentials were issued without a separate documented sandbox
        // API host — worth confirming with merchantsupport@gkash.com
        // whether this exact host is safe to test against with demo
        // credentials, or whether a distinct sandbox host exists, before
        // relying on GKASH_ENVIRONMENT=sandbox alone to keep test
        // transactions harmless.
        'base_url' => env('GKASH_BASE_URL', 'https://api.gkash.my'),
        'merchant_id' => env('GKASH_MERCHANT_ID'),
        'signature_key' => env('GKASH_SIGNATURE_KEY'),
        'environment' => env('GKASH_ENVIRONMENT', 'sandbox'),
        // Terminal ID — sent as `terminalID` on every checkout and recurring charge.
        'terminal_id' => env('GKASH_TERMINAL_ID'),
        // Payment methods shown on GKash's checkout page (`paymentmethod`).
        // Wash & Fold / Dry Cleaning bookings (and bag purchases):
        'payment_methods_booking' => env('GKASH_PAYMENT_METHODS_BOOKING', 'EWALLET,ECOMM,EBANKING'),
        // Subscriptions (sign-up, upgrade, card update): card only, because
        // only a card can be charged again automatically every month.
        'payment_methods_subscription' => env('GKASH_PAYMENT_METHODS_SUBSCRIPTION', 'ECOMM'),
    ],

    // Revenue Monster (open.revenuemonster.my). Keys: either a file path
    // (RM_PRIVATE_KEY_PATH / RM_PUBLIC_KEY_PATH, kept outside the web root)
    // or the PEM text itself (RM_PRIVATE_KEY / RM_PUBLIC_KEY, "\n" for new lines).
    'revenuemonster' => [
        'environment' => env('RM_ENVIRONMENT', 'production'), // production | sandbox
        'client_id' => env('RM_CLIENT_ID'),
        'client_secret' => env('RM_CLIENT_SECRET'),
        'store_id' => env('RM_STORE_ID'),
        'private_key' => env('RM_PRIVATE_KEY'),
        'private_key_path' => env('RM_PRIVATE_KEY_PATH'),
        'public_key' => env('RM_PUBLIC_KEY'),
        'public_key_path' => env('RM_PUBLIC_KEY_PATH'),
        // Booking checkout methods; empty = every method enabled on the account.
        'methods' => env('RM_PAYMENT_METHODS', ''),
    ],

    'telegram' => [
        // Create a bot via @BotFather on Telegram (free, ~2 minutes),
        // add it to whichever group/chat admin actually watches, then
        // get that chat's ID (easiest way: message the bot/group, then
        // GET https://api.telegram.org/bot<token>/getUpdates and read
        // the chat.id from the response). No per-message quota or cost
        // — this replaces OneSignal for admin-facing operational alerts
        // specifically because of that.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
    ],

    'rms' => [
        'merchant_id' => env('RMS_MERCHANT_ID', 'SB_payandwash'),
        'sub_merchant_id' => env('RMS_SUB_MERCHANT_ID', 'SB_payandwash'),
        'verify_key' => env('RMS_VERIFY_KEY', 'b1e09b3b0eac78420b766ed2d633479e'),
        'secret_key' => env('RMS_SECRET_KEY', '6e686bdf9acd298cbd606385d2f1500d'),  
        'environment' => env('RMS_ENVIRONMENT', 'sandbox'),
    ],

    'recurring' => [
        'rec_base_url' => env('RECURRING_BASE_URL', 'https://pay.fiuu.com'),
        // 'production' or 'sandbox' for the recurring account's hosted
        // payment page (sign-up checkout). Independent of FIUU_ENVIRONMENT,
        // which only applies to the normal Fiuu account. If unset, follows
        // RECURRING_BASE_URL (a "sandbox" host = sandbox, else production).
        'rec_environment' => env('RECURRING_ENVIRONMENT'),
        'rec_merchant_id' => env('RECURRING_MERCHANT_ID', 'paynwashsolutions_Dev'),
        'rec_verify_key' => env('RECURRING_VERIFY_KEY', '510533bdc88f6f68f219f32aa465d533'),
        'rec_secret_key' => env('RECURRING_SECRET_KEY', '9321d6ed1d1d8534156061aff3075a69'),  
    ],

    // Browser key for the admin panel's map picker (Maps JavaScript API +
    // Places API). Restrict it by HTTP referrer to app.automaid.asia/* in
    // Google Cloud Console — it is visible in the page source.
    'google_maps' => [
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    ],

];
