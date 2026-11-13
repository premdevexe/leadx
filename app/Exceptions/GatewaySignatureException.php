<?php

namespace App\Exceptions;

use Exception;

/**
 * A gateway webhook signature failed verification (or the payload was
 * malformed). The event must be rejected outright — never processed —
 * and the gateway re-sent.
 */
class GatewaySignatureException extends Exception
{
}
