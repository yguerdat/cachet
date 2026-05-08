<?php

namespace App\Mail;

use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Schedule $schedule,
        public readonly Subscriber $subscriber,
        public readonly bool $isUpdate,
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->isUpdate ? 'mail.schedule.subject_update' : 'mail.schedule.subject_new';

        return new Envelope(
            subject: __($subjectKey, ['title' => $this->schedule->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.schedule-notification',
            with: [
                'schedule' => $this->schedule,
                'subscriber' => $this->subscriber,
                'isUpdate' => $this->isUpdate,
                'scheduleUrl' => route('status-page.schedule', $this->schedule),
                'manageUrl' => route('subscribe.manage', $this->subscriber),
                'unsubscribeUrl' => route('subscribe.unsubscribe', $this->subscriber),
            ],
        );
    }
}
