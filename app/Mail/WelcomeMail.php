<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Mail\Mailable;

class WelcomeMail extends Mailable
{
    public function __construct(
        protected \App\Models\User|string $user,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $email = $this->user instanceof \App\Models\User ? $this->user->email : $this->user;
        $name  = $this->user instanceof \App\Models\User ? $this->user->name : 'Customer';

        $template = $this->template
            ?? EmailTemplate::where('key', 'customer.welcome')->first();

        return $this
            ->to($email, $name)
            ->subject($template?->subject ?? 'Welcome to '.config('app.name', 'LeadX'))
            ->markdown('emails.customer.welcome')
            ->with(['userName' => $name, 'template' => $template]);
    }
}
