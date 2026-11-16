<?php

/*
|--------------------------------------------------------------------------
| Push notifications
|--------------------------------------------------------------------------
|
| Credentials come ONLY from the environment — never commit them.
| Without credentials the app keeps working: in-app notifications are still
| created and push deliveries are recorded as "not_configured" (never "sent").
|
| FCM_PRIVATE_KEY may contain literal "\n" sequences (single-line .env value).
|
*/

return [

    'provider' => env('PUSH_PROVIDER', 'fcm'),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'client_email' => env('FCM_CLIENT_EMAIL'),
        'private_key' => env('FCM_PRIVATE_KEY'),
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'send_uri' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
        'timeout' => 10,
    ],

];
