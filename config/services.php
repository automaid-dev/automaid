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

    'rms' => [
        'merchant_id' => env('RMS_MERCHANT_ID', 'SB_payandwash'),
        'sub_merchant_id' => env('RMS_SUB_MERCHANT_ID', 'SB_payandwash'),
        'verify_key' => env('RMS_VERIFY_KEY', 'b1e09b3b0eac78420b766ed2d633479e'),
        'secret_key' => env('RMS_SECRET_KEY', '6e686bdf9acd298cbd606385d2f1500d'),  
        'environment' => env('RMS_ENVIRONMENT', 'sandbox'),
    ],

    'recurring' => [
        'rec_base_url' => env('RECURRING_BASE_URL', 'https://pay.fiuu.com'),
        'rec_merchant_id' => env('RECURRING_MERCHANT_ID', 'paynwashsolutions_Dev'),
        'rec_verify_key' => env('RECURRING_VERIFY_KEY', '510533bdc88f6f68f219f32aa465d533'),
        'rec_secret_key' => env('RECURRING_SECRET_KEY', '9321d6ed1d1d8534156061aff3075a69'),  
    ],

];
