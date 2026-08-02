<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class TestNotification implements ShouldQueue
{
    use Queueable;

    public static function send($user): void
    {
        $notification = Notification::make()
            ->title('Saved successfully')
            ->body('Your data has been saved.')
            ->success()
            ->viewData([
                'order_id' => 1233,
                'tracking_url' => 'https://tracking.example.com/123',
            ]);

        $data = $notification->getDatabaseMessage();

        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => get_class($notification),
            'data' => $data,
        ]);
    }


}
