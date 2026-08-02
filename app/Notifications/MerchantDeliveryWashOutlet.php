<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantDeliveryWashOutlet extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $job;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $job)
    {
        $this->user = $user;
        $this->job = $job;
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
                'title' => 'Rider is on the way',
                'message' => '📦 Heads up! Fresh laundry is on its way to you. Get those machines ready! 🚿 ',
            ];            
            $onesignal = new \App\Services\OneSignalService();
            $extra = ['assign_id' => $this->job->id, 'order_id' => $this->job->order_id];
            $send = $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, $extra, 1);

            // save notification
            if (isset($send['id']) && !empty($send['id']) && !isset($send['errors'])) {
                return [
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'assign_id' => $this->job->id,
                    'order_id' => $this->job->order_id,
                ];
            }   
        }
        return [];
    }
}
