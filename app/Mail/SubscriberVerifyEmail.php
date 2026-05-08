<?php

namespace App\Mail;

use Cachet\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberVerifyEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verify.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscriber-verify',
            with: [
                'subscriber' => $this->subscriber,
                'verifyUrl' => route('subscribe.verify-email', [
                    'subscriber' => $this->subscriber->getKey(),
                    'code' => $this->subscriber->verify_code,
                ]),
            ],
        );
    }
}
