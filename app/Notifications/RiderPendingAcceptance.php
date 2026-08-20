<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderPendingAcceptance extends Notification
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
        // Build the message first — this must still be saved to the
        // database notifications table (for the in-app notification
        // list) even when the rider has no device_id to push to, or
        // when the OneSignal push itself fails. Previously this whole
        // block only ran inside "if device_id" AND only returned data
        // if the push send succeeded — so a rider with no push token
        // registered yet (or a flaky push) got an empty notification
        // row, which looks identical to "no notification at all" in
        // the app.
        $data = [
            'title' => 'You’ve got new order',
            'message' => "You have booking order {$this->job->order_id} to accept.",
        ];

        // have device id — also send a push notification
        if ($this->user->device_id) {
            $onesignal = new \App\Services\OneSignalService();
            $extra = ['assign_id' => $this->job->id, 'order_id' => $this->job->order_id];
            $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, $extra, 1);
        }

        return [
            'title' => $data['title'],
            'message' => $data['message'],
            'assign_id' => $this->job->id,
            'order_id' => $this->job->order_id,
        ];
    }
}
