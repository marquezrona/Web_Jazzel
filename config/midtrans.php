<?php 

return [
 'serverKey' => env('MIDTRANS_SERVER_KEY'),
 'clientKey' => env('MIDTRANS_CLIENT_KEY'),
 'isProduction' => env('MIDTRANS_IS_PRODUCTION', false),
 'isSanitized' => env('MIDTRANS_SANITIZE', true),
 'is3ds' => env('MIDTRANS_3DS', true),
];