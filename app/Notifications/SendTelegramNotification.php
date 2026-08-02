<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendTelegramNotification extends Notification
{
    use Queueable;

    public $message;
    public $chat_id;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($chat_id, $message)
    {
        $this->chat_id = $chat_id;        
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    /**
     * [toTelegram description]
     * @param  [type] $notifiable [description]
     * @return [type]             [description]
     */
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to($this->chat_id)
            ->content($this->message);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
