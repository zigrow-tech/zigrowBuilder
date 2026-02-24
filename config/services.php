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


'payu' => [
    'key' => env('PAYU_MERCHANT_KEY'),
    'salt' => env('PAYU_MERCHANT_SALT'),
    'base_url' => env('PAYU_BASE_URL'),
    'success_url' => env('PAYU_SUCCESS_URL'),
    'failure_url' => env('PAYU_FAILURE_URL'),
],

'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('GOOGLE_REDIRECT_URI'),
],
'zoho' => [
    'client_id'=> env('ZOHO_CLIENT_ID'),
    'client_secret'=> env('ZOHO_CLIENT_SECRET'),
    'redirect_uri'=>env('ZOHO_REDIRECT_URI'),
],

 'unsplash' => [
    'access_key' => env('UNSPLASH_ACCESS_KEY'),
 ],
 'openai' => [
  'key' => env('OPENAI_API_KEY'),
 ],

 'facebook' => [
    'client_id' => env('FACEBOOK_CLIENT_ID'),
    'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    'redirect' => env('FACEBOOK_REDIRECT_URI'),
]

];
