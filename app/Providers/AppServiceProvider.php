<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\RazorpayGateway;
use App\Payments\SimulatorGateway;
use App\Push\FirebaseCloudMessagingProvider;
use App\Push\NullPushProvider;
use App\Push\PushNotificationProvider;
use App\Services\GoldRateService;
use App\Sms\DevelopmentOtpSender;
use App\Sms\Msg91OtpSender;
use App\Sms\OtpSender;
use App\Sms\UnconfiguredOtpSender;
use Illuminate\Support\Facades\Log;
use App\Services\GoldSavingPlanService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request: rates and plans are loaded from the
        // database once and shared by the header, footer, pages and sections.
        $this->app->scoped(GoldRateService::class);
        $this->app->scoped(GoldSavingPlanService::class);

        // Push provider: FCM when PUSH_PROVIDER=fcm (credentials checked at send time), otherwise none.
        $this->app->bind(PushNotificationProvider::class, fn () => match (config('push.provider')) {
            'fcm' => new FirebaseCloudMessagingProvider(config('push.fcm')),
            default => new NullPushProvider,
        });

        // SMS OTP sender (SMS_DRIVER). The development sender is only ever used when
        // APP_ENV is local/testing; anywhere else it is refused and OTPs cannot be sent.
        $this->app->bind(OtpSender::class, function () {
            $driver = config('sms.driver');

            if ($driver === 'development') {
                if ($this->app->environment(['local', 'testing'])) {
                    return new DevelopmentOtpSender((string) $this->app->environment());
                }

                Log::warning('SMS_DRIVER=development is ignored outside local/testing; no SMS provider is configured.');

                return new UnconfiguredOtpSender;
            }

            return $driver === 'msg91' ? new Msg91OtpSender(config('sms.drivers.msg91')) : new UnconfiguredOtpSender;
        });

        // The configured payment gateway. The simulator is refused in production.
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('payments.gateway')) {
                'simulator' => $this->app->isProduction()
                    ? throw new \LogicException('The payment simulator cannot be used in production.')
                    : new SimulatorGateway,
                default => new RazorpayGateway(config('payments.gateways.razorpay'), (string) config('payments.environment')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Customer password policy (Password::defaults() in validation rules).
        // In production, also reject passwords found in known data breaches.
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isProduction() ? $rule->mixedCase()->uncompromised() : $rule;
        });
    }
}
