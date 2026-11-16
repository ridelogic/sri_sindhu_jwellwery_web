<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountStatus;
use App\Enums\KycStatus;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\LoginController;
use App\Models\CustomerOtp;
use App\Models\CustomerPlan;
use App\Models\GoldTransaction;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\OtpService;
use App\Sms\DevelopmentOtpSender;
use App\Sms\Msg91OtpSender;
use App\Sms\OtpSender;
use App\Sms\UnconfiguredOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\Concerns\SignsInWithOtp;
use Tests\TestCase;

/**
 * OTP security: generation, storage, expiry, single use, replacement, attempt
 * and send limits, provider failures, and the development-only sender.
 */
class CustomerOtpSecurityTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithOtp;

    private const MOBILE = '9876543210';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDevelopmentSms();
        $this->customer = User::factory()->create(['mobile_number' => self::MOBILE]);
    }

    public function test_otp_is_six_digits_and_stored_only_as_a_keyed_hash(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $otp = CustomerOtp::query()->sole();
        $this->assertSame(self::MOBILE, $otp->mobile_number);
        $this->assertSame($this->customer->id, $otp->user_id);
        $this->assertSame(64, strlen($otp->code_hash));
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertStringNotContainsString($code, json_encode(CustomerOtp::query()->first()->getAttributes()));
        $this->assertEqualsWithDelta(now()->addMinutes(5)->getTimestamp(), $otp->expires_at->getTimestamp(), 2);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->submitLoginOtp($code)->assertSessionHasErrors(['otp' => LoginController::EXPIRED_OTP_MESSAGE]);

        $this->assertGuest('customer');
        $this->assertNotNull(CustomerOtp::query()->sole()->invalidated_at);
    }

    public function test_otp_is_invalidated_after_success_and_cannot_be_reused(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();
        $this->submitLoginOtp($code)->assertRedirect(route('customer.dashboard'));
        $this->assertNotNull(CustomerOtp::query()->sole()->consumed_at);

        // Reuse: sign out, start again with the SAME code.
        $this->post(route('customer.logout'));
        $this->assertSame(OtpService::INVALID, app(OtpService::class)->verify(self::MOBILE, OtpPurpose::Login, $code)['status']);

        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['mobile' => self::MOBILE]);
        if ($this->developmentOtp() !== $code) { // the new code is different; the old one must not work
            $this->submitLoginOtp($code)->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);
            $this->assertGuest('customer');
        }
    }

    public function test_requesting_a_new_otp_invalidates_the_previous_one(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $first = $this->developmentOtp();
        $this->post(route('login.resend')); // within the cooldown: refused, the first code stays valid
        $this->assertSame(1, CustomerOtp::query()->count());

        $this->travel(61)->seconds();
        $this->post(route('login.resend'))->assertRedirect(route('login'));
        $second = $this->developmentOtp();

        $this->assertSame(2, CustomerOtp::query()->count());
        $this->assertSame(1, CustomerOtp::query()->open()->count());
        if ($first !== $second) {
            $this->submitLoginOtp($first)->assertSessionHasErrors('otp');
        }
        $this->submitLoginOtp($second)->assertRedirect(route('customer.dashboard'));
    }

    public function test_otp_is_locked_after_five_wrong_attempts(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->submitLoginOtp($wrong)->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);
        }
        $this->submitLoginOtp($wrong)->assertSessionHasErrors(['otp' => LoginController::LOCKED_OTP_MESSAGE]);

        // Even the correct code no longer works.
        $this->submitLoginOtp($code)->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);
        $this->assertGuest('customer');
        $this->assertSame(5, CustomerOtp::query()->sole()->attempts);
    }

    public function test_otp_requests_have_a_cooldown_and_an_hourly_limit(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $this->post(route('login.resend'))->assertSessionHasErrors('otp');
        $this->assertStringContainsString('Please wait', session('errors')->first('otp'));
        $this->get(route('login'))->assertSee('data-resend-countdown="', false)->assertSee('Resend OTP in');

        for ($i = 2; $i <= 5; $i++) {
            $this->travel(61)->seconds();
            $this->post(route('login.resend'))->assertSessionHasNoErrors();
        }
        $this->assertSame(5, CustomerOtp::query()->count());

        $this->travel(61)->seconds();
        $this->post(route('login.resend'))->assertSessionHasErrors('otp');
        $this->assertStringContainsString('Too many OTP requests', session('errors')->first('otp'));
        $this->assertSame(5, CustomerOtp::query()->count()); // no sixth SMS

        // Unregistered numbers are limited exactly the same way (no enumeration through limits).
        $this->post(route('login.cancel'));
        $this->post(route('login.store'), ['mobile' => '9123456789']);
        $this->post(route('login.resend'))->assertSessionHasErrors('otp');
        $this->assertStringContainsString('Please wait', session('errors')->first('otp'));
    }

    public function test_verifying_requires_the_server_side_pending_number_and_ignores_forged_ids(): void
    {
        $victim = User::factory()->create(['mobile_number' => '9811111111']);

        // No pending sign-in: nothing to verify.
        $this->post(route('login.verify'), ['otp' => '123456', 'mobile' => '9811111111', 'user_id' => $victim->id])
            ->assertRedirect(route('login'));
        $this->assertGuest('customer');

        // The attacker verifies their OWN number; submitted ids/numbers for someone else are ignored.
        $this->requestLoginOtp(self::MOBILE);
        $this->from(route('login'))->post(route('login.verify'), [
            'otp' => $this->developmentOtp(), 'user_id' => $victim->id, 'mobile' => '9811111111', 'id' => $victim->id,
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($this->customer, 'customer');
        $this->assertFalse(auth('customer')->user()->is($victim));
    }

    public function test_inactive_and_blocked_customers_receive_no_session(): void
    {
        $this->customer->forceFill(['status' => AccountStatus::Inactive])->save();
        $this->otpLogin(self::MOBILE)->assertRedirect(route('login'))->assertSessionHasErrors(['mobile' => LoginController::INACTIVE_MESSAGE]);
        $this->assertGuest('customer');
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));

        $this->customer->forceFill(['status' => AccountStatus::Blocked])->save();
        $this->otpLogin(self::MOBILE)->assertSessionHasErrors(['mobile' => LoginController::BLOCKED_MESSAGE]);
        $this->assertGuest('customer');
    }

    public function test_unconfigured_sms_provider_fails_safely(): void
    {
        config(['sms.driver' => null]);
        $this->assertInstanceOf(UnconfiguredOtpSender::class, app(OtpSender::class));

        $this->from(route('login'))->post(route('login.store'), ['mobile' => self::MOBILE])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['mobile' => LoginController::SEND_FAILED_MESSAGE])
            ->assertSessionMissing('status');

        // Never pretends it was sent: no usable OTP, no OTP step, retry allowed immediately.
        $this->assertSame(0, CustomerOtp::query()->open()->count());
        $this->get(route('login'))->assertDontSee('name="otp"', false);
        $this->assertSame(0, app(OtpService::class)->cooldownRemaining(self::MOBILE, OtpPurpose::Login));
    }

    public function test_msg91_sender_posts_the_template_request_and_reports_failures(): void
    {
        config(['sms.driver' => 'msg91', 'sms.drivers.msg91.auth_key' => 'test-auth-key', 'sms.drivers.msg91.template_id' => 'tmpl-123']);
        Http::preventStrayRequests();
        Http::fake(['control.msg91.com/*' => Http::sequence()
            ->push(['type' => 'success', 'message' => 'ok'])
            ->push(['type' => 'error', 'message' => 'Invalid template'], 400)]);
        $this->assertInstanceOf(Msg91OtpSender::class, app(OtpSender::class));

        $this->requestLoginOtp(self::MOBILE)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertNull($this->developmentOtp()); // the real sender never hands the code back
        $this->get(route('login'))->assertDontSee('data-otp-development', false);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://control.msg91.com/api/v5/flow'
            && $request->hasHeader('authkey', 'test-auth-key')
            && $request['template_id'] === 'tmpl-123'
            && $request['recipients'][0]['mobiles'] === '91'.self::MOBILE
            && preg_match('/^\d{6}$/', $request['recipients'][0]['otp']));

        // Provider rejects the message → customer told it was not sent; code unusable; nothing secret logged.
        Log::spy();
        $this->post(route('login.cancel'));
        $this->travel(61)->seconds();
        $this->from(route('login'))->post(route('login.store'), ['mobile' => self::MOBILE])->assertSessionHasErrors(['mobile' => LoginController::SEND_FAILED_MESSAGE]);
        $this->assertSame(0, CustomerOtp::query()->open()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context === ['provider' => 'msg91', 'reason' => 'rejected', 'http_status' => 400]);
    }

    public function test_development_sender_cannot_be_used_in_production(): void
    {
        $this->expectException(LogicException::class);
        new DevelopmentOtpSender('production');
    }

    public function test_development_driver_is_ignored_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['sms.driver' => 'development']);

        $this->assertInstanceOf(UnconfiguredOtpSender::class, app(OtpSender::class));
    }

    public function test_development_code_is_only_shown_for_the_development_driver(): void
    {
        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();
        $this->get(route('login'))->assertSee('data-otp-development', false)->assertSee('Development only');

        // A stale flash value is never rendered when another driver is configured.
        config(['sms.driver' => 'msg91']);
        $this->withSession(['otp_development_code' => $code])->get(route('login'))->assertDontSee('data-otp-development', false)->assertDontSee($code);
    }

    public function test_existing_customer_data_is_untouched_by_otp_sign_in(): void
    {
        $scheme = CustomerPlan::factory()->for($this->customer)->create();
        KycProfile::factory()->for($this->customer)->create(['kyc_status' => KycStatus::Verified]);
        Payment::factory()->for($scheme, 'customerPlan')->create(['user_id' => $this->customer->id]);
        $password = $this->customer->getRawOriginal('password');
        $snapshot = fn () => [
            CustomerPlan::query()->orderBy('id')->get()->map->getAttributes()->all(),
            KycProfile::query()->orderBy('id')->get()->map->getAttributes()->all(),
            Payment::query()->orderBy('id')->get()->map->getAttributes()->all(),
            GoldTransaction::query()->count(),
        ];
        $before = $snapshot();

        $this->otpLogin(self::MOBILE)->assertRedirect(route('customer.dashboard'));
        $this->get(route('customer.dashboard'))->assertOk();

        $this->assertEquals($before, $snapshot());
        $this->assertSame($password, $this->customer->fresh()->getRawOriginal('password')); // existing password data kept, not deleted
    }
}
