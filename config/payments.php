<?php

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
|
| Credentials come ONLY from the environment — never commit real keys.
|
| PAYMENT_GATEWAY      razorpay (default) | simulator (local development only)
| PAYMENT_GATEWAY_ENV  sandbox (test keys) | live
|
*/

return [

    'gateway' => env('PAYMENT_GATEWAY', 'razorpay'),

    'environment' => env('PAYMENT_GATEWAY_ENV', 'sandbox'),

    'currency' => 'INR',

    /*
     * Purity credited for scheme payments. Plans do not carry a purity yet;
     * gold saving schemes are for jewellery, which is 22K. Stored on every
     * gold transaction so the record stays self-explanatory.
     */
    'gold_purity' => '22K',

    /*
     * An unfinished attempt for the same installment is reused (no second
     * gateway order) when the customer clicks "Pay" again within this window.
     */
    'reuse_attempt_minutes' => 30,

    'gateways' => [
        'razorpay' => [
            'key' => env('PAYMENT_GATEWAY_KEY'),
            'secret' => env('PAYMENT_GATEWAY_SECRET'),
            'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
            'base_url' => 'https://api.razorpay.com/v1',
            'checkout_script' => 'https://checkout.razorpay.com/v1/checkout.js',
            'timeout' => 15,
        ],
    ],

];
