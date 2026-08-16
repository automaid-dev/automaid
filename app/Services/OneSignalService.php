<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected $appId;
    protected $apiKey;
    protected $subdomain;

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->subdomain = config('services.onesignal.subdomain');
    }

    /**
     * [sendEmail description]
     * @param  [type] $email   [description]
     * @param  [type] $subject [description]
     * @param  [type] $content [description]
     * @return [type]          [description]
     */
    public function sendEmail($email, $subject, $content)
    {
        $config = config('services.onesignal.customer');
        $this->appId = $config['app_id']; 
        $this->apiKey = $config['api_key'];

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->apiKey,
            'Content-Type'  => 'application/json; charset=utf-8',
        ])->post("https://api.onesignal.com/notifications?c=email", [
            'app_id'    => $this->appId,
            'include_email_tokens' => [$email],
            'include_unsubscribed' => true, // Bypasses subscription status check for tokens
            'email_subject' => $subject,
            'email_body' => $content,
        ]);
        return $response->json();
    }

    /**
     * [sendOneSignalNotification description]
     * @param  [type] $title          [description]
     * @param  [type] $message        [description]
     * @param  [type] $playerId       [description]
     * @param  [type] $additionalData [description]
     * @return [type]                 [description]
     */
    function sendOneSignalNotification($title, $message, $playerId = null, $additionalData = null, $typeId = null)
    {
        $config = config('services.onesignal.customer');
        if ($typeId) {
            $config = config('services.onesignal.merchant');
        }
        $this->appId = $config['app_id'];        
        $this->apiKey = $config['api_key'];

        $fields = [
            'app_id' => $this->appId,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'priority' => 10,
        ];

        // Add custom data if provided
        if (!empty($additionalData)) {
            $fields['data'] = $additionalData;
        }

        // Send to specific device
        if ($playerId) {
            $fields['include_player_ids'] = [$playerId];
        } 
        
        // Broadcast to all users
        else {
            $fields['included_segments'] = ['All'];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $this->apiKey,
        ])->post('https://api.onesignal.com/notifications', $fields);
        return $response->json();
    }

    /**
     * Central place for "notify this one user about this one thing" —
     * creates the in-app Notification record (always, regardless of
     * whether email/push succeed), sends the email, and attempts a push
     * notification ONLY if the user has a real device_id registered.
     *
     * IMPORTANT: sendOneSignalNotification() broadcasts to ALL users
     * when $playerId is null/empty (see included_segments => ['All']
     * above) — this method deliberately never calls it without a
     * confirmed non-empty device_id, to avoid ever accidentally
     * notifying every single user about one person's booking/purchase/etc.
     *
     * @param  \App\Models\User $user
     * @param  string $type      One of Notification::* constants
     * @param  string $title
     * @param  string $body
     * @param  string $emailContent  Rendered HTML for the email body
     * @param  int|null $orderId
     * @return void
     */
    public function notifyUser($user, string $type, string $title, string $body, string $emailContent, ?int $orderId = null)
    {
        try {
            \App\Models\CustomerNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'order_id' => $orderId,
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to create in-app notification', ['error' => $th->getMessage(), 'user_id' => $user->id, 'type' => $type]);
        }

        try {
            $this->sendEmail($user->email, $title, $emailContent);
        } catch (\Throwable $th) {
            Log::error('Failed to send notification email', ['error' => $th->getMessage(), 'user_id' => $user->id, 'type' => $type]);
        }

        if (!empty($user->device_id)) {
            try {
                $this->sendOneSignalNotification($title, $body, $user->device_id);
            } catch (\Throwable $th) {
                Log::error('Failed to send push notification', ['error' => $th->getMessage(), 'user_id' => $user->id, 'type' => $type]);
            }
        }
    }

}




