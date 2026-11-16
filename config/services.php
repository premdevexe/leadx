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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pusher' => [
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
        'instance_id' => env('PUSHER_INSTANCE_ID'),
        'device_interest' => env('PUSHER_DEVICE_INTEREST', 'hello'),
        'server_key' => env('PUSHER_SERVER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (Flutter employee app)
    |--------------------------------------------------------------------------
    |
    | Server-side FCM for the mobile employee app. The service-account JSON is
    | referenced by a FILE PATH kept on the server (never in the repo / .env):
    |
    |   FCM_CREDENTIALS_PATH=/etc/leadx/firebase/leadx-eb5c5-firebaseadmin.json
    |
    | Only the file path and project id go in .env; the private key stays in
    | the referenced file. FcmService::isConfigured() is false until that file
    | exists, so the whole FCM path degrades silently when unset.
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID', 'leadx-eb5c5'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
        // Android notification channel id used for mobile delivery.
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'leadx_general'),
    ],

];
