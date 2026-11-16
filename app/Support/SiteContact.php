<?php

namespace App\Support;

/**
 * Read-only access to the store's contact details from config/site.php.
 *
 * Every accessor returns null when a value is not configured, so views can
 * show a neutral placeholder instead of invented business information.
 */
class SiteContact
{
    public const PLACEHOLDER = 'To be announced';

    public static function phone(): ?string
    {
        return self::value('phone');
    }

    public static function phoneHref(): ?string
    {
        $phone = self::phone();

        return $phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null;
    }

    public static function email(): ?string
    {
        return self::value('email');
    }

    public static function emailHref(): ?string
    {
        $email = self::email();

        return $email ? 'mailto:'.$email : null;
    }

    public static function whatsappHref(?string $message = null): ?string
    {
        $number = preg_replace('/\D/', '', (string) self::value('whatsapp'));

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.($message ? '?text='.rawurlencode($message) : '');
    }

    public static function address(): ?string
    {
        return self::value('address');
    }

    public static function hours(): ?string
    {
        return self::value('hours');
    }

    public static function mapEmbedUrl(): ?string
    {
        return self::value('map_embed_url');
    }

    /**
     * Configured social profiles only — unconfigured networks return null.
     *
     * @return array<string, ?string>
     */
    public static function social(): array
    {
        return array_map(fn ($url) => filled($url) ? $url : null, (array) config('site.social', []));
    }

    public static function appUrl(string $platform): ?string
    {
        $url = config("site.app.$platform");

        return filled($url) ? $url : null;
    }

    private static function value(string $key): ?string
    {
        $value = config("site.contact.$key");

        return filled($value) ? trim((string) $value) : null;
    }
}
