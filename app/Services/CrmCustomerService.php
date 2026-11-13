<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRM customer upsert, driven exclusively by verified purchases.
 *
 * The matching policy (config leadx.crm.match) is applied in order:
 *   1. normalized phone (within the order's team, when the order has one)
 *   2. (normalized phone, normalized email) pair
 * Repeat purchases always land on the same customer row; the purchase
 * history (count, total, last date) is updated atomically. Unpaid or
 * pending orders are never a reason to create or promote a customer.
 */
class CrmCustomerService
{
    /**
     * Find-or-create the CRM customer for a verified purchase and record
     * the purchase on top of it.
     */
    public function upsertForPurchase(SalesOrder $order, int $verifiedCents, ?int $createdByAdminId = null): Customer
    {
        $name = trim((string) $order->customer_name);

        $customer = $this->match($order)
            ?? $this->create($order, $name, $createdByAdminId);

        // A verified purchase is what promotes an enquiry to paying status.
        if ($customer->status !== 'paying') {
            $customer->status = 'paying';
        }

        // Purchase history: count + exact total in cents.
        $customer->purchases_count = (int) $customer->purchases_count + 1;
        $customer->total_purchased = Money::format(Money::toCents($customer->total_purchased) + $verifiedCents);
        $customer->last_purchase_at = now();
        $customer->save();

        // Back-link the customer on the order's payment context.
        return $customer;
    }

    /**
     * Match an existing customer using the established policy.
     */
    public function match(SalesOrder $order): ?Customer
    {
        $phone = $this->normalizePhone($order->customer_phone);
        $email = $this->normalizeEmail($order->customer_email);

        $query = Customer::query()->where('team_id', $order->team_id ?? null);

        // Policy step 1: phone.
        if ($phone !== null) {
            $found = (clone $query)->where('normalized_phone', $phone)->first();

            if ($found) {
                return $found;
            }
        }

        // Policy step 2: phone + email pair.
        if ($phone !== null && $email !== null) {
            return (clone $query)
                ->where('normalized_phone', $phone)
                ->where('normalized_email', $email)
                ->first();
        }

        return null;
    }

    protected function create(SalesOrder $order, string $name, ?int $createdByAdminId): Customer
    {
        if (! config('leadx.crm.auto_create_on_verified_purchase', true)) {
            throw ValidationException::withMessages([
                'customer' => 'No matching customer and auto-creation is disabled by policy.',
            ]);
        }

        $phone = $this->normalizePhone($order->customer_phone);
        $email = $this->normalizeEmail($order->customer_email ?? $order->customer_name);

        // Seed at zero: the caller (upsertForPurchase) immediately records
        // this verified purchase on top, so the first purchase lands at a
        // count of exactly one.
        return Customer::create([
            'name' => $name,
            'email' => $order->customer_email ?? null,
            'phone' => $order->customer_phone,
            'normalized_phone' => $phone,
            'normalized_email' => $email,
            'status' => 'paying',
            'team_id' => $order->team_id,
            'created_by_admin_id' => $createdByAdminId,
            'purchases_count' => 0,
            'total_purchased' => '0.00',
        ]);
    }

    /**
     * E.164-ish normalization: digits only, leading + dropped, trailing
     * country code kept as-is (no country inference — matching stays exact).
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $phone);

        if (strlen($digits) < 7) {
            return null; // too short to be a real phone
        }

        return ltrim($digits, '+');
    }

    /**
     * Lower-case trimmed email, or null when blank.
     */
    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }
}