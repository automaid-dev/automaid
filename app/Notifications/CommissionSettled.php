<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Deliberately NOT `implements ShouldQueue` — see the systemic fix
// applied to every other rider/merchant notification in this app:
// ShouldQueue defers sending to a real queue worker, and this
// deployment has never actually had one running, so every queued
// notification silently sat unprocessed forever. Running synchronously
// (like this) is what actually works here.
class CommissionSettled extends Notification
{
    use Queueable;

    public $commission;
    public $amount;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($commission, $amount)
    {
        $this->commission = $commission;
        $this->amount = $amount;
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
        $data = [
            'title' => '💰 Commission settled',
            'message' => "RM{$this->amount} of your pending commission has been paid out.",
        ];

        // have device id — also send a push notification
        if ($this->commission->user && $this->commission->user->device_id) {
            $onesignal = new \App\Services\OneSignalService();
            $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->commission->user->device_id, [], 1);
        }

        return [
            'title' => $data['title'],
            'message' => $data['message'],
        ];
    }
}
