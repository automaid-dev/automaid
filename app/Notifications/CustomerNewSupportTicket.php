<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerNewSupportTicket extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $ticket;
    public $type;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $ticket, $type)
    {
        $this->user = $user;
        $this->ticket = $ticket;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // have device id
        if ($this->user->device_id) {

            // send push notification            
            $data = [
                'title' => 'New message from customer support',
                'message' => 'Tap to view the message',
            ];
            $onesignal = new \App\Services\OneSignalService();

            if ($this->type == 1) {
                $send = $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, null);
            }
            else {
                $send = $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, null, 1);
            }

            // save notification
            if (isset($send['id']) && !empty($send['id']) && !isset($send['errors'])) {
                return [
                    'title' => $data['title'],
                    'message' => $data['message'],
                ];
            }            
        }
        return [];
    }
}
