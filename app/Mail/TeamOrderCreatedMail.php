<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class TeamOrderCreatedMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'team.order_created')->first();

        return $this
            ->subject($template?->subject ?? 'New Order Assigned — #'.$this->order->order_number)
            ->markdown('emails.team.order-created')
            ->with(['order' => $this->order, 'template' => $template]);
    }
}
