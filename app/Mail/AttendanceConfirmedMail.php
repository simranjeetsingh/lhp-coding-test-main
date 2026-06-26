<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $attendeeName,
        public readonly array $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're registered: {$this->event['name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendance-confirmed');
    }
}
