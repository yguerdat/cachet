<?php

namespace App\Mail;

use Cachet\Models\Incident;
use Cachet\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncidentNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Incident $incident,
        public readonly Subscriber $subscriber,
        public readonly bool $isUpdate,
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->isUpdate ? 'mail.incident.subject_update' : 'mail.incident.subject_new';

        return new Envelope(
            subject: __($subjectKey, ['title' => $this->incident->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.incident-notification',
            with: [
                'incident' => $this->incident,
                'subscriber' => $this->subscriber,
                'isUpdate' => $this->isUpdate,
                'incidentUrl' => route('cachet.status-page.incident', $this->incident),
                'manageUrl' => route('subscribe.manage', $this->subscriber),
                'unsubscribeUrl' => route('subscribe.unsubscribe', $this->subscriber),
            ],
        );
    }
}
