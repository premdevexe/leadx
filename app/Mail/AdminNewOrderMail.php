<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class AdminNewOrderMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'admin.new_order')->first();

        return $this
            ->subject($template?->subject ?? 'New Order — #'.$this->order->order_number)
            ->markdown('emails.admin.new-order')
            ->with(['order' => $this->order, 'template' => $template]);
    }
}
