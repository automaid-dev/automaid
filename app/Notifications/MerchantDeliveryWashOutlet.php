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

    public function __construct($user, $job)
    {
        $this->user = $user;
        $this->job = $job;
    }

    /**
     * See CustomerReadyPickup for why this changed — same bug across
     * all 7 notification classes in this lifecycle.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Rider is on the way')
                    ->line('Fresh laundry is on its way to you. Get those machines ready!')
                    ->action('View Order', url('/'))
                    ->line('Thank you for using Automaid!');
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'title' => 'Rider is on the way',
            'message' => '📦 Heads up! Fresh laundry is on its way to you. Get those machines ready! 🚿 ',
        ];

        if ($this->user->device_id) {
            try {
                $onesignal = new \App\Services\OneSignalService();
                $extra = ['assign_id' => $this->job->id, 'order_id' => $this->job->order_id];
                $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, $extra, 1);
            } catch (\Throwable $th) {
                \Log::error('push notification failed', ['error' => $th->getMessage(), 'notification' => 'MerchantDeliveryWashOutlet']);
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
