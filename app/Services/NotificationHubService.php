<?php

namespace App\Services;

use App\Events\NotificationReceived;
use App\Jobs\SendWebPushNotificationJob;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Notifications\ClosingValueApprovalNotification;
use App\Notifications\GeneralOrderNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class NotificationHubService
{
    public function __construct(
        protected BeamsService $beams,
        protected EmailService $email
    ) {}

    /**
     * Order created event.
     */
    public function notifyOrderCreated(SalesOrder $order): void
    {
        $employee = $order->assignedEmployee;

        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'New Order Attributed',
                message: "You have been assigned to order {$order->order_number} ({$order->customer_name}).",
                order: $order,
                action: 'created'
            ));

            $this->sendBeamsSafely(
                title: 'New Order Attributed',
                body: "Order {$order->order_number} has been assigned to you.",
                interests: ['employee-'.$employee->id],
                deduplicationKey: 'order_created_emp_'.$order->id
            );

            $this->broadcastSafely($employee, [
                'title' => 'New Order Attributed',
                'message' => "You have been assigned to order {$order->order_number} ({$order->customer_name}).",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'created',
                'type' => 'order',
            ]);
        }

        $this->sendBeamsSafely(
            title: 'New LeadX Order',
            body: "Order {$order->order_number} created for {$order->customer_name} (\${$order->closing_value}).",
            interests: ['admin'],
            deduplicationKey: 'order_created_admin_'.$order->id
        );
    }

    /**
     * Closing value approval requested.
     */
    public function notifyClosingApprovalRequested(SalesOrder $order): void
    {
        $employeeName = $order->assignedEmployee?->name ?? 'Sales Rep';

        $this->sendBeamsSafely(
            title: 'Closing Approval Requested',
            body: "Order {$order->order_number} ({$employeeName}): \${$order->closing_value} is below floor price \${$order->minimum_closing_price_snapshot}.",
            interests: ['admin'],
            deduplicationKey: 'closing_requested_'.$order->id
        );
    }

    /**
     * Closing value approval decision.
     */
    public function notifyClosingApprovalDecision(SalesOrder $order, string $decision, ?string $notes = null): void
    {
        $employee = $order->assignedEmployee;
        if (! $employee) {
            return;
        }

        $employee->notify(new ClosingValueApprovalNotification($order, $decision, $notes));

        $this->sendBeamsSafely(
            title: $decision === 'accepted' ? 'Closing Value Approved' : 'Closing Value Rejected',
            body: "Super Admin {$decision} closing value for {$order->order_number}.",
            interests: ['employee-'.$employee->id],
            deduplicationKey: 'closing_decision_'.$order->id.'_'.$decision
        );

        $this->broadcastSafely($employee, [
            'title' => $this->closingValueTitle($decision),
            'message' => $this->closingValueMessage($order, $decision, $notes),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => $decision,
            'type' => 'closing_value',
        ]);
    }

    /**
     * Payment verified notification.
     */
    public function notifyPaymentVerified(Payment $payment): void
    {
        $order = $payment->order;
        if (! $order) {
            return;
        }

        $employee = $order->assignedEmployee;
        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'Payment Verified',
                message: "Payment of \${$payment->amount} verified for order {$order->order_number}.",
                order: $order,
                action: 'payment_verified'
            ));

            $this->sendBeamsSafely(
                title: 'Payment Verified',
                body: "Payment of \${$payment->amount} verified for {$order->order_number}.",
                interests: ['employee-'.$employee->id],
                deduplicationKey: 'payment_verified_'.$payment->id
            );

            $this->broadcastSafely($employee, [
                'title' => 'Payment Verified',
                'message' => "Payment of \${$payment->amount} verified for order {$order->order_number}.",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'payment_verified',
                'type' => 'payment',
            ]);
        }
    }

    /**
     * Subscription activated notification.
     */
    public function notifySubscriptionActivated(Subscription $subscription): void
    {
        $order = $subscription->order;
        if (! $order) {
            return;
        }

        $employee = $order->assignedEmployee;
        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'Subscription Activated',
                message: "Subscription for {$order->order_number} is now active (expires {$subscription->expires_at?->toDateString()}).",
                order: $order,
                action: 'subscription_activated'
            ));

            $this->sendBeamsSafely(
                title: 'Subscription Activated',
                body: "Subscription for {$order->order_number} activated.",
                interests: ['employee-'.$employee->id, 'admin'],
                deduplicationKey: 'sub_activated_'.$subscription->id
            );

            $this->broadcastSafely($employee, [
                'title' => 'Subscription Activated',
                'message' => "Subscription for {$order->order_number} is now active (expires {$subscription->expires_at?->toDateString()}).",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'subscription_activated',
                'type' => 'subscription',
            ]);
        }
    }

    /**
     * Subscription expiring soon reminder.
     */
    public function notifySubscriptionExpiring(Subscription $subscription, int $daysRemaining): void
    {
        $order = $subscription->order;
        if (! $order) {
            return;
        }

        $employee = $order->assignedEmployee;
        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'Renewal Reminder',
                message: "Order {$order->order_number} subscription expires in {$daysRemaining} days. Contact customer for renewal.",
                order: $order,
                action: 'expiring_soon'
            ));

            $this->broadcastSafely($employee, [
                'title' => 'Renewal Reminder',
                'message' => "Order {$order->order_number} subscription expires in {$daysRemaining} days. Contact customer for renewal.",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'expiring_soon',
                'type' => 'renewal',
            ]);
        }
    }

    /**
     * Order cancelled notification.
     */
    public function notifyOrderCancelled(SalesOrder $order, string $reason): void
    {
        $employee = $order->assignedEmployee;
        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'Order Cancelled',
                message: "Order {$order->order_number} has been cancelled. Reason: {$reason}",
                order: $order,
                action: 'cancelled'
            ));

            $this->sendBeamsSafely(
                title: 'Order Cancelled',
                body: "Order {$order->order_number} has been cancelled.",
                interests: ['employee-'.$employee->id],
                deduplicationKey: 'order_cancelled_'.$order->id
            );

            $this->broadcastSafely($employee, [
                'title' => 'Order Cancelled',
                'message' => "Order {$order->order_number} has been cancelled. Reason: {$reason}",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'cancelled',
                'type' => 'order',
            ]);
        }
    }

    /**
     * Refund processed notification.
     */
    public function notifyRefundProcessed(Refund $refund): void
    {
        $order = $refund->order;
        if (! $order) {
            return;
        }

        $employee = $order->assignedEmployee;
        if ($employee) {
            $employee->notify(new GeneralOrderNotification(
                title: 'Refund Processed',
                message: "A refund of \${$refund->amount} was processed on order {$order->order_number}.",
                order: $order,
                action: 'refund'
            ));

            $this->sendBeamsSafely(
                title: 'Refund Processed',
                body: "A refund of \${$refund->amount} was processed on order {$order->order_number}.",
                interests: ['employee-'.$employee->id],
                deduplicationKey: 'refund_processed_'.$refund->id
            );

            $this->broadcastSafely($employee, [
                'title' => 'Refund Processed',
                'message' => "A refund of \${$refund->amount} was processed on order {$order->order_number}.",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action' => 'refund',
                'type' => 'refund',
            ]);
        }
    }

    /**
     * Asynchronously publish a Web Push Notification to Pusher Beams via queue.
     *
     * Dispatches SendWebPushNotificationJob, which is configured with afterCommit = true
     * so that push notifications are never dispatched if the surrounding database
     * transaction rolls back.
     */
    protected function sendBeamsSafely(string $title, string $body, array $interests, ?string $deduplicationKey = null): void
    {
        try {
            SendWebPushNotificationJob::dispatch($title, $body, $interests, $deduplicationKey);
        } catch (\Throwable $e) {
            // Queue dispatch failure must never interrupt business transactions.
            Log::warning('Web push notification job dispatch failed', [
                'title' => $title,
                'interests' => $interests,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Non-blocking Pusher Channels broadcast helper.
     */
    protected function broadcastSafely(Authenticatable $user, array $data): void
    {
        try {
            broadcast(new NotificationReceived($user, $data))->toOthers();
        } catch (\Throwable) {
            // Broadcast failure must never interrupt the business transaction.
        }
    }

    private function closingValueTitle(string $decision): string
    {
        return $decision === 'accepted' ? 'Closing Value Approved' : 'Closing Value Rejected';
    }

    private function closingValueMessage(SalesOrder $order, string $decision, ?string $notes): string
    {
        return $decision === 'accepted'
            ? "Super Admin approved the closing value for order {$order->order_number}."
            : "Super Admin rejected the closing value for order {$order->order_number}. Reason: {$notes}";
    }
}
