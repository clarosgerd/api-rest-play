<?php

return [

    'base_auth_url' => env('SIP_BASE_AUTH_URL', 'http://127.0.0.1:800/api/v1/autenticacion/generarToken'),
    'base_api_url' => env('SIP_BASE_API_URL', 'http://127.0.0.1:800/api/v1/'),
    'username' => env('SIP_USERNAME'),
    'password' => env('SIP_PASSWORD'),
    'apikey_servicio' => env('SIP_APIKEY_SERVICIO'),
    'moneda' => 'BOB',
    'tipo_solicitud' => 'API',
    'callback_url' => env('SIP_CALLBACK_URL'),
    'callback_basic_user' => env('SIP_CALLBACK_BASIC_USER'),
    'callback_basic_password' => env('SIP_CALLBACK_BASIC_PASSWORD'),

];
