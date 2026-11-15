<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

/**
 * Order confirmation — sent to the customer immediately after an order
 * is created. Communicates order receipt, not subscription activation.
 */
class OrderConfirmationMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
        protected array $context = [],
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'order.confirmation')->first();

        $subject = $template?->subject ?? "Order Confirmed — #{$this->order->order_number}";

        return $this
            ->subject($subject)
            ->markdown('emails.order.confirmation')
            ->with([
                'order' => $this->order,
                'template' => $template,
                'context' => $this->context,
            ]);
    }
}
