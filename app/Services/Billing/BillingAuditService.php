<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class BillingAuditService
{
    public function log(
        Model $subject,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        ?string $ip = null
    ): void {
        AuditLog::create([
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip' => $ip ?? request()?->ip(),
        ]);
    }
}
