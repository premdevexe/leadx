<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\EmailNotificationPreference;
use App\Models\EmailReminderTracking;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed the email_notification_preferences and email_templates tables with
 * the complete default set.
 *
 * Idempotent: running it multiple times never creates duplicates.
 *
 * Usage: php artisan email:seed-defaults
 */
class SeedEmailDefaultsCommand extends Command
{
    protected $signature = 'email:seed-defaults';
    protected $description = 'Seed default email notification preferences and templates';

    public function handle(): int
    {
        DB::transaction(function (): void {
            $this->seedPreferences();
            $this->seedTemplates();
        });

        $this->info('Email defaults seeded.');

        return self::SUCCESS;
    }

    private function seedPreferences(): void
    {
        foreach (EmailService::EVENTS as $eventKey => $meta) {
            EmailNotificationPreference::updateOrCreate(
                ['event_key' => $eventKey],
                [
                    'label'       => $meta['label'],
                    'category'    => $meta['category'],
                    'template_key' => $meta['template_key'],
                    'is_enabled'  => true,
                ]
            );
        }

        $this->line('Seeded '.count(EmailService::EVENTS).' notification preferences.');
    }

    private function seedTemplates(): void
    {
        $defaults = [
            // ── Customer templates ──────────────────────────────────────
            [
                'key'         => 'customer.welcome',
                'name'        => 'Welcome Email',
                'category'    => 'customer',
                'subject'     => 'Welcome to {{app_name}}, {{customer_name}}!',
                'body'        => <<<'BLADE'
<h2>Welcome {{customer_name}}!</h2>
<p>Thank you for joining {{app_name}}. We are excited to have you on board.</p>
<p>If you have any questions, reply to this email or contact us at {{support_email}}.</p>
BLADE,
                'description' => 'Sent when a new customer account is created.',
            ],
            [
                'key'         => 'customer.registered',
                'name'        => 'Registration Confirmation',
                'category'    => 'customer',
                'subject'     => 'Confirm Your Registration — {{app_name}}',
                'body'        => <<<'BLADE'
<h2>Registration Confirmed</h2>
<p>Hi {{customer_name}}, your account has been successfully created.</p>
<p>You can now place orders and manage your subscriptions through our platform.</p>
BLADE,
                'description' => 'Confirms a successful customer registration.',
            ],
            [
                'key'         => 'order.confirmation',
                'name'        => 'Order Confirmation',
                'category'    => 'customer',
                'subject'     => 'Order Confirmed — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Order Confirmed</h2>
<p>Thank you for your order, {{customer_name}}.</p>
<table>
  <tr><td><strong>Order #</strong></td><td>{{order_number}}</td></tr>
  <tr><td><strong>Product</strong></td><td>{{product_name}}</td></tr>
  <tr><td><strong>Duration</strong></td><td>{{product_duration}}</td></tr>
  <tr><td><strong>Amount</strong></td><td>{{closing_value}}</td></tr>
  <tr><td><strong>Payment Method</strong></td><td>{{payment_method}}</td></tr>
  <tr><td><strong>Status</strong></td><td>{{payment_status}}</td></tr>
</table>
BLADE,
                'description' => 'Sent to the customer after an order is created.',
            ],
            [
                'key'         => 'payment.pending',
                'name'        => 'Payment Pending',
                'category'    => 'customer',
                'subject'     => 'Payment Pending — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Pending</h2>
<p>Hi {{customer_name}}, we have received your order <strong>{{order_number}}</strong>.</p>
<p>Payment is currently <strong>{{payment_status}}</strong>. Please complete payment to activate your subscription.</p>
BLADE,
                'description' => 'Informs the customer that payment is still pending.',
            ],
            [
                'key'         => 'payment.verified',
                'name'        => 'Payment Verified',
                'category'    => 'customer',
                'subject'     => 'Payment Verified — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Verified!</h2>
<p>Hi {{customer_name}}, we have verified your payment for order <strong>{{order_number}}</strong>.</p>
<p>Your subscription will be activated shortly. Thank you!</p>
BLADE,
                'description' => 'Sent when the admin verifies a customer payment.',
            ],
            [
                'key'         => 'subscription.activated',
                'name'        => 'Subscription Activated',
                'category'    => 'customer',
                'subject'     => 'Subscription Activated — {{product_name}}',
                'body'        => <<<'BLADE'
<h2>Subscription Activated!</h2>
<p>Congratulations {{customer_name}}!</p>
<p>Your subscription has been activated.</p>
<table>
  <tr><td><strong>Product</strong></td><td>{{product_name}}</td></tr>
  <tr><td><strong>Plan</strong></td><td>{{plan_name}}</td></tr>
  <tr><td><strong>Activation Date</strong></td><td>{{activation_date}}</td></tr>
  <tr><td><strong>Expires On</strong></td><td>{{expiry_date}}</td></tr>
  <tr><td><strong>Billing Cycle</strong></td><td>{{billing_cycle}}</td></tr>
</table>
BLADE,
                'description' => 'Sent when a subscription transitions to active.',
            ],
            [
                'key'         => 'order.api_key_delivered',
                'name'        => 'API Key Delivery',
                'category'    => 'customer',
                'subject'     => 'Your API Key — Order {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Your API Key</h2>
<p>Hi {{customer_name}}, here is your API key for order <strong>{{order_number}}</strong>:</p>
<pre style="background:#f4f4f4;padding:12px;border-radius:6px;">{{api_key}}</pre>
<p>Keep this key secure. Do not share it with anyone.</p>
BLADE,
                'description' => 'Delivers the order API key to the customer.',
            ],
            [
                'key'         => 'subscription.expiring',
                'name'        => 'Subscription Expiring',
                'category'    => 'customer',
                'subject'     => 'Your Subscription Expires in {{days_before}} Days',
                'body'        => <<<'BLADE'
<h2>Subscription Expiring Soon</h2>
<p>Hi {{customer_name}},</p>
<p>Your subscription for <strong>{{product_name}}</strong> will expire on <strong>{{expiry_date}}</strong> ({{days_before}} days from now).</p>
<p>Renew now to avoid service interruption.</p>
BLADE,
                'description' => 'Advance warning that the subscription is about to expire.',
            ],
            [
                'key'         => 'subscription.expired',
                'name'        => 'Subscription Expired',
                'category'    => 'customer',
                'subject'     => 'Subscription Expired — {{product_name}}',
                'body'        => <<<'BLADE'
<h2>Subscription Expired</h2>
<p>Hi {{customer_name}},</p>
<p>Your subscription for <strong>{{product_name}}</strong> expired on <strong>{{expiry_date}}</strong>.</p>
<p>Renew now to restore access.</p>
BLADE,
                'description' => 'Sent when a subscription has already expired.',
            ],
            [
                'key'         => 'subscription.renewed',
                'name'        => 'Renewal Confirmation',
                'category'    => 'customer',
                'subject'     => 'Subscription Renewed — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Subscription Renewed!</h2>
<p>Hi {{customer_name}},</p>
<p>Your subscription has been renewed successfully.</p>
<table>
  <tr><td><strong>Order #</strong></td><td>{{order_number}}</td></tr>
  <tr><td><strong>Product</strong></td><td>{{product_name}}</td></tr>
  <tr><td><strong>New Expiry Date</strong></td><td>{{expiry_date}}</td></tr>
  <tr><td><strong>Billing Cycle</strong></td><td>{{billing_cycle}}</td></tr>
</table>
BLADE,
                'description' => 'Confirms a successful subscription renewal.',
            ],
            [
                'key'         => 'payment.receipt',
                'name'        => 'Payment Receipt',
                'category'    => 'customer',
                'subject'     => 'Payment Receipt — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Receipt</h2>
<p>Hi {{customer_name}}, here is your payment receipt for order <strong>{{order_number}}</strong>.</p>
<table>
  <tr><td><strong>Amount Paid</strong></td><td>{{closing_value}}</td></tr>
  <tr><td><strong>Payment Method</strong></td><td>{{payment_method}}</td></tr>
  <tr><td><strong>Status</strong></td><td>{{payment_status}}</td></tr>
</table>
BLADE,
                'description' => 'Sends a payment receipt to the customer.',
            ],
            [
                'key'         => 'support.response',
                'name'        => 'Support Response',
                'category'    => 'customer',
                'subject'     => 'Update on Your Request — {{support_topic}}',
                'body'        => <<<'BLADE'
<h2>Update on Your Request</h2>
<p>Hi {{customer_name}},</p>
<p>We have a response to your request <strong>{{support_topic}}</strong>.</p>
<p><strong>Status:</strong> {{support_outcome}}</p>
<p>{{support_note}}</p>
<p>If you have further questions, just reply to this email.</p>
<p>Thanks,<br>{{app_name}} Team</p>
BLADE,
                'description' => 'Notifies the customer that their support request received a response.',
            ],

            // ── Team templates ─────────────────────────────────────────
            [
                'key'         => 'team.order_created',
                'name'        => 'New Team Order',
                'category'    => 'team',
                'subject'     => 'New Order — {{order_number}} ({{team_name}})',
                'body'        => <<<'BLADE'
<h2>New Order Assigned</h2>
<p>A new order has been created for your team.</p>
<table>
  <tr><td><strong>Order #</strong></td><td>{{order_number}}</td></tr>
  <tr><td><strong>Customer</strong></td><td>{{customer_name}}</td></tr>
  <tr><td><strong>Product</strong></td><td>{{product_name}}</td></tr>
  <tr><td><strong>Amount</strong></td><td>{{closing_value}}</td></tr>
</table>
BLADE,
                'description' => 'Notifies the team when a new order is created.',
            ],
            [
                'key'         => 'team.payment_status_updated',
                'name'        => 'Payment Status Updated (Team)',
                'category'    => 'team',
                'subject'     => 'Payment Status Updated — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Status Update</h2>
<p>The payment status for order <strong>{{order_number}}</strong> has changed.</p>
<p><strong>New Status:</strong> {{payment_status}}</p>
BLADE,
                'description' => 'Notifies the team of a payment status change.',
            ],
            [
                'key'         => 'team.renewal_followup',
                'name'        => 'Customer Renewal Follow-up (Team)',
                'category'    => 'team',
                'subject'     => 'Renewal Follow-up — {{customer_name}}',
                'body'        => <<<'BLADE'
<h2>Renewal Follow-up Required</h2>
<p>Customer <strong>{{customer_name}}</strong>'s subscription for <strong>{{product_name}}</strong> is approaching renewal.</p>
<p><strong>Renewal Date:</strong> {{renewal_date}}</p>
BLADE,
                'description' => 'Prompts the team to follow up on an upcoming renewal.',
            ],

            // ── Employee templates ─────────────────────────────────────
            [
                'key'         => 'employee.order_created',
                'name'        => 'Order Created (Employee)',
                'category'    => 'employee',
                'subject'     => 'New Order Assigned — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>New Order Assigned to You</h2>
<p>Order <strong>{{order_number}}</strong> has been assigned to you.</p>
<p><strong>Customer:</strong> {{customer_name}}</p>
<p><strong>Product:</strong> {{product_name}}</p>
BLADE,
                'description' => 'Notifies the assigned employee of a new order.',
            ],
            [
                'key'         => 'employee.assigned_customer',
                'name'        => 'Assigned Customer (Employee)',
                'category'    => 'employee',
                'subject'     => 'New Customer Assigned — {{customer_name}}',
                'body'        => <<<'BLADE'
<h2>New Customer Assigned</h2>
<p>Customer <strong>{{customer_name}}</strong> has been assigned to you.</p>
BLADE,
                'description' => 'Notifies an employee of a newly assigned customer.',
            ],
            [
                'key'         => 'employee.payment_update',
                'name'        => 'Payment Update (Employee)',
                'category'    => 'employee',
                'subject'     => 'Payment Update — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Update</h2>
<p>A payment for order <strong>{{order_number}}</strong> has been updated.</p>
<p><strong>Status:</strong> {{payment_status}}</p>
BLADE,
                'description' => 'Notifies the assigned employee of a payment change.',
            ],
            [
                'key'         => 'employee.followup',
                'name'        => 'Follow-up Reminder (Employee)',
                'category'    => 'employee',
                'subject'     => 'Follow-up Reminder — {{customer_name}}',
                'body'        => <<<'BLADE'
<h2>Follow-up Reminder</h2>
<p>This is a reminder to follow up with customer <strong>{{customer_name}}</strong> for order <strong>{{order_number}}</strong>.</p>
BLADE,
                'description' => 'Reminds an employee to follow up with a customer.',
            ],

            // ── Admin templates ────────────────────────────────────────
            [
                'key'         => 'admin.new_customer',
                'name'        => 'New Customer Registration (Admin)',
                'category'    => 'admin',
                'subject'     => 'New Customer Registered — {{customer_name}}',
                'body'        => <<<'BLADE'
<h2>New Customer Registration</h2>
<p>A new customer has registered:</p>
<p><strong>Name:</strong> {{customer_name}}</p>
<p><strong>Email:</strong> {{customer_email}}</p>
BLADE,
                'description' => 'Notifies admins of a new customer registration.',
            ],
            [
                'key'         => 'admin.new_order',
                'name'        => 'New Order (Admin)',
                'category'    => 'admin',
                'subject'     => 'New Order — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>New Order Created</h2>
<table>
  <tr><td><strong>Order #</strong></td><td>{{order_number}}</td></tr>
  <tr><td><strong>Customer</strong></td><td>{{customer_name}}</td></tr>
  <tr><td><strong>Product</strong></td><td>{{product_name}}</td></tr>
  <tr><td><strong>Team</strong></td><td>{{team_name}}</td></tr>
  <tr><td><strong>Amount</strong></td><td>{{closing_value}}</td></tr>
</table>
BLADE,
                'description' => 'Notifies admins when a new order is placed.',
            ],
            [
                'key'         => 'admin.payment_verify',
                'name'        => 'Payment Verification Required (Admin)',
                'category'    => 'admin',
                'subject'     => 'Payment Verification Required — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Payment Verification Required</h2>
<p>A payment for order <strong>{{order_number}}</strong> needs verification.</p>
<p><strong>Customer:</strong> {{customer_name}}</p>
BLADE,
                'description' => 'Prompts admin to verify a pending payment.',
            ],
            [
                'key'         => 'admin.payment_failed',
                'name'        => 'Failed Payment (Admin)',
                'category'    => 'admin',
                'subject'     => 'Failed Payment — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Failed Payment</h2>
<p>A payment for order <strong>{{order_number}}</strong> has failed.</p>
<p><strong>Customer:</strong> {{customer_name}}</p>
BLADE,
                'description' => 'Alerts admin of a failed payment.',
            ],
            [
                'key'         => 'admin.subscription_expiry',
                'name'        => 'Subscription Expiry Alert (Admin)',
                'category'    => 'admin',
                'subject'     => 'Subscription Expiring — {{order_number}}',
                'body'        => <<<'BLADE'
<h2>Subscription Expiring</h2>
<p>Subscription for order <strong>{{order_number}}</strong> is expiring.</p>
<p><strong>Customer:</strong> {{customer_name}}</p>
<p><strong>Expires On:</strong> {{expiry_date}}</p>
BLADE,
                'description' => 'Alerts admin of an upcoming subscription expiry.',
            ],
            [
                'key'         => 'admin.system_failure',
                'name'        => 'System Email Failure (Admin)',
                'category'    => 'admin',
                'subject'     => '[ALERT] LeadX Email Delivery Failure',
                'body'        => <<<'BLADE'
<h2>Email Delivery Failure</h2>
<p>LeadX failed to deliver an email. Please investigate.</p>
<p><strong>Recipient:</strong> {{recipient}}</p>
<p><strong>Subject:</strong> {{subject}}</p>
<p><strong>Error:</strong> {{error}}</p>
BLADE,
                'description' => 'Internal alert when an email delivery fails permanently.',
            ],
        ];

        foreach ($defaults as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                array_merge($template, ['is_default' => true, 'is_active' => true])
            );
        }

        $this->line('Seeded '.count($defaults).' default email templates.');
    }
}
