<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a verified purchase cannot be attributed to a CRM customer
 * because no match exists and auto-creation is disabled by policy. The
 * payment verification is still recorded; only the customer upsert is
 * deferred so the admin can resolve the customer manually.
 */
class CrmCustomerUnresolvableException extends Exception
{
}
