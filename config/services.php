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

    'lexoffice' => [
        'base_uri'   => env('LEXOFFICE_BASE_URI', 'https://api.lexoffice.io/v1'),
        'api_key'    => env('LEXOFFICE_API_KEY', 'a7TNU.2Lg_VIifspNhLvFlB-U7Jjf32OO4joIL_Eq_.iIppn'),
        // Set to false in local dev if PHP has no CA bundle (Windows cURL issue)
        'verify_ssl' => env('LEXOFFICE_VERIFY_SSL', true),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'wawi' => [
        'sync_token' => env('WAWI_SYNC_TOKEN', 'changeme'),
    ],

    'getraenkedb' => [
        'url' => env('GETRAENKEDB_API_URL', 'http://89.167.121.25:8800/api/v1'),
        'key' => env('GETRAENKEDB_API_KEY', ''),
    ],

    'ninox' => [
        'api_key' => env('NINOX_API_KEY'),
        'team_id' => env('NINOX_TEAM_ID', 'yzW23724nQbqCQX9R'),
        // kehr (aktuell) — Kunden, Mitarbeiter, Veranstaltung, Kassenbuch …
        'db_id_kehr' => env('NINOX_DB_ID_KEHR', 'tpwd0lln7f65'),
        // alte DB — ProduktDB, WaWi, Tourenplanung …
        'db_id_alt'  => env('NINOX_DB_ID_ALT', 'fadrrq8poh9b'),
        // Rückwärtskompatibilität
        'db_id'      => env('NINOX_DB_ID', 'tpwd0lln7f65'),
    ],

    'gmail' => [
        'client_id'     => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
        'redirect_uri'  => env('GMAIL_REDIRECT_URI', config('app.url') . '/admin/communications/settings/gmail-callback'),
    ],

];
