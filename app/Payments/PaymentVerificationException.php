<?php

namespace App\Payments;

use RuntimeException;

/**
 * A gateway response failed server-side verification (bad signature, wrong
 * order, amount or currency). The payment must not be credited.
 */
class PaymentVerificationException extends RuntimeException {}
