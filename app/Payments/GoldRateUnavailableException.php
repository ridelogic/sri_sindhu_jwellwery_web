<?php

namespace App\Payments;

use RuntimeException;

/**
 * No active gold rate applies to the payment time. The payment is held for
 * review: a rate is never guessed.
 */
class GoldRateUnavailableException extends RuntimeException {}
