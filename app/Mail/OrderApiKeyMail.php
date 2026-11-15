<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class OrderApiKeyMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected string $apiKey,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'order.api_key_delivered')->first();

        return $this
            ->subject($template?->subject ?? 'Your API Key — #'.$this->order->order_number)
            ->markdown('emails.order.api-key')
            ->with(['order' => $this->order, 'apiKey' => $this->apiKey, 'template' => $template]);
    }
}
