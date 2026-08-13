<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $subject;
    public $body;

    /**
     * Create a new message instance.
     *
     * @param string|null $body Rendered HTML body — if omitted, falls back
     *                          to the original hardcoded welcome wording.
     */
    public function __construct(string $name, string $email, string $subject, ?string $body = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->body = $body ?? "<p>Dear <strong>{$name}</strong>,</p><p>Welcome to Auto Maid! Your account is ready.</p>";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.custom',
            with: [
                'subject' => $this->subject,
                'body' => $this->body,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
