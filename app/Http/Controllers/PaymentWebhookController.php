<?php

namespace App\Http\Controllers;

use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server gateway notifications (e.g. Razorpay "payment.captured").
 * This is what credits a payment when the customer closes the browser before
 * returning from checkout. Authenticity is proven by the webhook signature;
 * completion is idempotent, so repeated deliveries are harmless.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $adapter = app(PaymentGateway::class);
        abort_unless($adapter->name() === $gateway, 404);

        $verification = $adapter->parseWebhook($request->getContent(), $request->header('X-Razorpay-Signature'));

        if ($verification === null) {
            return response()->json(['status' => 'ignored'], 400);
        }

        $payment = app(PaymentService::class)->handleWebhook($verification);

        return response()->json(['status' => $payment ? 'processed' : 'unknown-order']);
    }
}
