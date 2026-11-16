<?php

/*
|--------------------------------------------------------------------------
| Public Website Settings
|--------------------------------------------------------------------------
|
| Brand, contact and social details used across the public website.
|
| Contact and social values are intentionally EMPTY by default. Until real
| details are set in .env, the website shows a neutral "to be announced"
| state rather than invented business information. Set these in .env:
|
|   SITE_PHONE="+91 ..."            SITE_EMAIL="..."
|   SITE_WHATSAPP="91XXXXXXXXXX"     SITE_ADDRESS="..."
|   SITE_HOURS="Mon – Sat, ..."      SITE_MAP_EMBED_URL="https://www.google.com/maps/embed?..."
|   SITE_INSTAGRAM_URL / SITE_FACEBOOK_URL / SITE_YOUTUBE_URL
|   SITE_APP_ANDROID_URL / SITE_APP_IOS_URL
|
*/

return [

    'name' => 'Sri Sindhu Jewellery',

    'tagline' => 'Gold Saving Scheme & Fine Jewellery',

    'description' => 'Discover Sri Sindhu Jewellery Gold Saving Scheme. Save monthly, track your gold savings and build towards the jewellery you love.',

    'contact' => [
        'phone' => env('SITE_PHONE'),
        'email' => env('SITE_EMAIL'),
        'whatsapp' => env('SITE_WHATSAPP'), // International format digits, e.g. 919XXXXXXXXX
        'address' => env('SITE_ADDRESS'),
        'hours' => env('SITE_HOURS'),
        'map_embed_url' => env('SITE_MAP_EMBED_URL'),
    ],

    'social' => [
        'instagram' => env('SITE_INSTAGRAM_URL'),
        'facebook' => env('SITE_FACEBOOK_URL'),
        'youtube' => env('SITE_YOUTUBE_URL'),
    ],

    'app' => [
        'android' => env('SITE_APP_ANDROID_URL'),
        'ios' => env('SITE_APP_IOS_URL'),
    ],

];
