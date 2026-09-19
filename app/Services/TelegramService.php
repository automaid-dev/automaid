<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a message to the admin Telegram chat via the Bot API. Deliberately
 * tiny — just the one method this app actually needs (sendMessage), not a
 * general-purpose Telegram client. See config/services.php's 'telegram'
 * block for how to get the bot token and chat ID.
 */
class TelegramService
{
    protected ?string $botToken;
    protected ?string $adminChatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->adminChatId = config('services.telegram.admin_chat_id');
    }

    /**
     * @param  string $message  Supports Telegram's HTML parse mode (<b>, <i>, <a href>, etc.)
     * @return bool
     */
    public function notifyAdmin(string $message): bool
    {
        if (!$this->botToken || !$this->adminChatId) {
            Log::warning('TelegramService::notifyAdmin skipped — bot token or admin chat ID not configured.');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->adminChatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if (!$response->successful()) {
                Log::warning('TelegramService::notifyAdmin failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $th) {
            // Same reasoning as every other admin-notification call site
            // in this app — a failed notification must never bubble up
            // and break whatever operation was trying to send it.
            Log::warning('TelegramService::notifyAdmin exception', ['message' => $th->getMessage()]);
            return false;
        }
    }
}
