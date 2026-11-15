<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class PaymentStatusUpdatedMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected string $status,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'team.payment_status_updated')->first();

        return $this
            ->subject($template?->subject ?? 'Payment Status Updated — #'.$this->order->order_number)
            ->markdown('emails.team.payment-status')
            ->with(['order' => $this->order, 'status' => $this->status, 'template' => $template]);
    }
}
