<?php

namespace App\Payments;

use RuntimeException;

/**
 * The gateway could not be reached or refused the request. Messages are safe
 * to show customers and never contain credentials.
 */
class PaymentGatewayException extends RuntimeException {}
