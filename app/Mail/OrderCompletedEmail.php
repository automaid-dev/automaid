<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCompletedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $subject;
    public $order;

    /**
     * [__construct description]
     * @param [type] $name    [description]
     * @param [type] $subject [description]
     * @param [type] $order   [description]
     */
    public function __construct($name, $subject, $order)
    {
        $this->name = $name;
        $this->subject = $subject;
        $this->order = $order;
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
            view: 'emails.order-completed',            
            with: [
                'name' => $this->name,
                'order_id' => $this->order->id,
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
