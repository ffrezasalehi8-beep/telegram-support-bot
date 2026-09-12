<?php

declare(strict_types=1);

final class Telegram
{
    private string $apiUrl;

    public function __construct(string $token)
    {
        if ($token === '') {
            throw new InvalidArgumentException('BOT_TOKEN is empty.');
        }

        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }

    /**
     * @throws RuntimeException
     */
    public function request(string $method, array $parameters = []): array
    {
        $payload = json_encode(
            $parameters,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: Railway-Telegram-Support-Bot/1.0',
                ],
                'content'       => $payload,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(
            $this->apiUrl . $method,
            false,
            $context
        );

        if ($response === false) {
            throw new RuntimeException(
                "Telegram API request failed: {$method}"
            );
        }

        try {
            $decoded = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Invalid Telegram API response: {$method}",
                0,
                $exception
            );
        }

        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            $description = $decoded['description'] ?? 'Unknown Telegram error';

            throw new RuntimeException(
                "Telegram API error in {$method}: {$description}"
            );
        }

        return $decoded;
    }

    public function sendMessage(
        string|int $chatId,
        string $text,
        array $extra = []
    ): array {
        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
        ], $extra));
    }

    public function answerCallbackQuery(
        string $callbackQueryId,
        string $text = ''
    ): array {
        $parameters = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== '') {
            $parameters['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $parameters);
    }
}
