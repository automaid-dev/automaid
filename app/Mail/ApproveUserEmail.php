<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApproveUserEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $subject;

    /**
     * [__construct description]
     * @param string $name    [description]
     * @param string $subject [description]
     */
    public function __construct(string $name, string $subject)
    {
        $this->name = $name;
        $this->subject = $subject;
    }

    /**
     * [envelope description]
     * @return [type] [description]
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * [content description]
     * @return [type] [description]
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.approve-user',
            with: [
                'name' => $this->name,
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
