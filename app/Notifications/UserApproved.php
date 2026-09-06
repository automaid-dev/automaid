<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Deliberately NOT `implements ShouldQueue` — same reasoning as every
// other rider/merchant notification in this app: this deployment has
// never had a queue worker running, so queued notifications just sit
// unprocessed forever. Sending synchronously is what actually works.
//
// `via()` only returns ['database'] — the admin's "approve" action
// (UserResource.php) already sends the push notification + email via
// OneSignalService::notifyUser() separately, so adding a mail/push
// channel here would double-send both.
class UserApproved extends Notification
{
    use Queueable;

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
     * Get the array representation of the notification — this is what
     * actually lands in the app's in-app Notifications list.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => '✅ You\'re approved!',
            'message' => "You're approved. Please keep on duty to receive any new order.",
        ];
    }
}
