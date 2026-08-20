<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantOrderDelivered extends Notification
{
    use Queueable;

    public $user;
    public $job;

    public function __construct($user, $job)
    {
        $this->user = $user;
        $this->job = $job;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Order delivered')
                    ->line('Another happy customer served! Thanks for making laundry day a breeze!')
                    ->action('View Order', url('/'))
                    ->line('Thank you for using Automaid!');
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'title' => 'Order delivered',
            'message' => '📦 Another happy customer served! Thanks for making laundry day a breeze! 🧺✨',
        ];

        if ($this->user->device_id) {
            try {
                $onesignal = new \App\Services\OneSignalService();
                $extra = ['assign_id' => $this->job->id, 'order_id' => $this->job->order_id];
                $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, $extra, 1);
            } catch (\Throwable $th) {
                \Log::error('push notification failed', ['error' => $th->getMessage(), 'notification' => 'MerchantOrderDelivered']);
            }
        }

        return [
            'title' => $data['title'],
            'message' => $data['message'],
            'assign_id' => $this->job->id,
            'order_id' => $this->job->order_id,
        ];
    }
}
