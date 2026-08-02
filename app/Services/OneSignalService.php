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
            'Authorization' => 'Basic ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post("https://onesignal.com/api/v1/notifications", [
            'app_id'    => $this->appId,
            'include_email_tokens' => [$email],
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
            'Authorization' => 'Basic ' . $this->apiKey,
        ])->post('https://onesignal.com/api/v1/notifications', $fields);
        return $response->json();
    }



}




