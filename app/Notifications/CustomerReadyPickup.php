<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class CustomerReadyPickup extends Notification
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
     * Was ['database'] only — meaning toMail() below never ran despite
     * being fully written, and (see toArray()) the database record
     * itself only got saved when a push notification also succeeded.
     * With device_id never populated (native push SDK integration
     * still pending), that meant this notification produced nothing
     * at all, through any channel, every single time.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Order is confirmed')
                    ->line('Your laundry pickup is confirmed! Our rider is prepping to scoop up your clothes soon.')
                    ->action('View Order', url('/'))
                    ->line('Thank you for using Automaid!');
    }

    /**
     * Always returns the real content now, and always attempts push
     * when a device is registered — but neither the database record
     * nor the email above depend on push succeeding anymore.
     */
    public function toArray(object $notifiable): array
    {
        $data = [
            'title' => 'Order is confirmed',
            'message' => '✅ Your laundry pickup is confirmed! Our rider is prepping to scoop up your clothes soon. 🧳',
        ];

        if ($this->user->device_id) {
            try {
                $onesignal = new \App\Services\OneSignalService();
                $extra = ['assign_id' => $this->job->id, 'order_id' => $this->job->order_id];
                $onesignal->sendOneSignalNotification($data['title'], $data['message'], $this->user->device_id, $extra);
            } catch (\Throwable $th) {
                \Log::error('push notification failed', ['error' => $th->getMessage(), 'notification' => 'CustomerReadyPickup']);
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
