<?php

namespace App\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firebase Cloud Messaging (HTTP v1 API) using a service account — no SDK.
 *
 * Auth: a short-lived OAuth2 access token is obtained by signing a JWT with the
 * service account's private key (RS256) and cached for 50 minutes.
 *
 * Errors are reported as safe summaries; responses, tokens and credentials
 * are never included in error text.
 */
class FirebaseCloudMessagingProvider implements PushNotificationProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @param  array{project_id: ?string, client_email: ?string, private_key: ?string, token_uri: string, send_uri: string, timeout: int}  $config */
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'fcm';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['project_id'] ?? null)
            && filled($this->config['client_email'] ?? null)
            && $this->privateKey() !== null;
    }

    public function send(string $token, PushMessage $message): PushResult
    {
        if (! $this->isConfigured()) {
            return PushResult::notConfigured();
        }

        try {
            $accessToken = $this->accessToken();
        } catch (Throwable) {
            return PushResult::failed('Could not authenticate with the push provider.');
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) $this->config['timeout'])
                ->post(sprintf($this->config['send_uri'], rawurlencode((string) $this->config['project_id'])), [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $message->title, 'body' => $message->body],
                        'data' => array_map('strval', $message->data),
                    ],
                ]);
        } catch (ConnectionException) {
            return PushResult::failed('The push provider could not be reached.');
        }

        if ($response->successful()) {
            return PushResult::sent();
        }

        $code = (string) ($response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? '');
        $invalid = $response->status() === 404 || in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true);

        return PushResult::failed($invalid ? 'Device token is no longer valid.' : 'The push provider rejected the message (HTTP '.$response->status().').', $invalid);
    }

    private function accessToken(): string
    {
        $cacheKey = 'push:fcm:access-token:'.hash('sha256', (string) $this->config['client_email']);

        return Cache::remember($cacheKey, 3000, function () {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $this->config['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $this->config['token_uri'],
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            if (! openssl_sign(implode('.', $segments), $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Signing failed.');
            }
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout((int) $this->config['timeout'])->post($this->config['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            return (string) ($response->json('access_token') ?? throw new \RuntimeException('No access token.'));
        });
    }

    private function privateKey(): ?\OpenSSLAsymmetricKey
    {
        $pem = str_replace('\n', "\n", (string) ($this->config['private_key'] ?? ''));

        if ($pem === '') {
            return null;
        }

        $key = @openssl_pkey_get_private($pem);

        return $key === false ? null : $key;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
