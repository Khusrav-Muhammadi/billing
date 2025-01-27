<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    public function sendMessage(int $recipient_id, string $message)
    {
        $telegramApiUrl = env('TELEGRAMM_BOT_API') . env('TELEGRAMM_BOT_TOKEN') . "/sendMessage";

        $data = [
            'chat_id' => $recipient_id,
            'text' => $message
        ];

        Http::post($telegramApiUrl, $data);
    }
}
