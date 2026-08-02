<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerNewAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $announcement;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $announcement)
    {
        $this->user = $user;
        $this->announcement = $announcement;
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
                'title' => 'Auto Maid',
                'message' => $this->announcement->title,
            ];
            $onesignal = new \App\Services\OneSignalService();
            $send = $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, null);

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
