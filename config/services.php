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

    // Vendas importadas do Moloni (conta da exploracao agricola, nao a da
    // Ateneya). Credenciais de programador em moloni.pt > Area de Cliente >
    // Programadores; o utilizador e a palavra-passe sao os de entrar no Moloni.
    'moloni' => [
        'url' => env('MOLONI_URL', 'https://api.moloni.pt/v1'),
        'client_id' => env('MOLONI_CLIENT_ID'),
        'client_secret' => env('MOLONI_CLIENT_SECRET'),
        'username' => env('MOLONI_USERNAME'),
        'password' => env('MOLONI_PASSWORD'),
        // Vazio: usa a unica empresa da conta (ou a primeira).
        'company_id' => env('MOLONI_COMPANY_ID'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

];
