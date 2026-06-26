<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $attendeeName,
        public readonly array $event,
        public readonly string $reminderType, // '3d' or '24h'
    ) {}

    public function envelope(): Envelope
    {
        $when = $this->reminderType === '24h' ? 'tomorrow' : 'in 3 days';

        return new Envelope(subject: "Reminder: {$this->event['name']} is {$when}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder');
    }
}
