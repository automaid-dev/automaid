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

    /**
     * Create a new message instance.
     */
    public function __construct(string $name, string $email, string $subject)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
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
            view: 'emails.register',
            with: [
                'name' => $this->name,
                'email' => $this->email,
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
