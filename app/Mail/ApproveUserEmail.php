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
    public $body;

    /**
     * [__construct description]
     * @param string $name    [description]
     * @param string $subject [description]
     * @param string|null $body Rendered HTML body — if omitted, falls back
     *                          to the original hardcoded wording (kept for
     *                          any other code still constructing this
     *                          Mailable the old way).
     */
    public function __construct(string $name, string $subject, ?string $body = null)
    {
        $this->name = $name;
        $this->subject = $subject;
        $this->body = $body ?? "<p>Dear <strong>{$name}</strong>,</p><p>Congratulations, your application has been approved! Please login to your app now and start taking orders now.</p><p>If you have any issues or enquries, please do not hesitate to contact our customer support via the mobile app. We are happy to assist you.</p>";
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
