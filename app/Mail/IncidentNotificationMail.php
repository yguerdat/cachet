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
        $latestUpdate = $this->incident->updates()->latest('created_at')->first();
        $displayStatus = $latestUpdate?->status ?? $this->incident->status;
        $displayMessage = $latestUpdate?->message ?? $this->incident->message;

        return new Content(
            markdown: 'mail.incident-notification',
            with: [
                'incident' => $this->incident,
                'subscriber' => $this->subscriber,
                'isUpdate' => $this->isUpdate,
                'displayStatus' => $displayStatus,
                'displayMessage' => $displayMessage,
                'statusColor' => $this->statusColor($displayStatus),
                'incidentUrl' => route('cachet.status-page.incident', $this->incident),
                'manageUrl' => route('subscribe.manage', $this->subscriber),
                'unsubscribeUrl' => route('subscribe.unsubscribe', $this->subscriber),
            ],
        );
    }

    private function statusColor(mixed $status): string
    {
        $name = $status instanceof \BackedEnum ? $status->name : (string) $status;

        return match ($name) {
            'investigating' => '#eab308',
            'identified' => '#f97316',
            'watching' => '#3b82f6',
            'fixed' => '#22c55e',
            default => '#71717a',
        };
    }
}
