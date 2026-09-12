<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Telegram.php';

$botToken      = trim((string) getenv('BOT_TOKEN'));
$webhookSecret = trim((string) getenv('WEBHOOK_SECRET'));
$appUrl        = trim((string) getenv('APP_URL'));
$railwayDomain = trim((string) getenv('RAILWAY_PUBLIC_DOMAIN'));

if ($botToken === '') {
    fwrite(STDERR, "BOT_TOKEN is missing.\n");
    exit(1);
}

if ($webhookSecret === '') {
    fwrite(STDERR, "WEBHOOK_SECRET is missing.\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $webhookSecret)) {
    fwrite(
        STDERR,
        "WEBHOOK_SECRET contains unsupported characters.\n"
    );

    exit(1);
}

/*
 * اگر APP_URL تعریف نشده باشد، دامنهٔ خود Railway استفاده می‌شود.
 */
if ($appUrl === '' && $railwayDomain !== '') {
    $appUrl = 'https://' . $railwayDomain;
}

if ($appUrl === '') {
    fwrite(
        STDOUT,
        "No public domain found. Webhook setup skipped.\n"
    );

    exit(0);
}

if (!preg_match('#^https://#i', $appUrl)) {
    $appUrl = 'https://' . $appUrl;
}

$webhookUrl = rtrim($appUrl, '/') . '/webhook';

try {
    $telegram = new Telegram($botToken);

    $result = $telegram->request('setWebhook', [
        'url'                  => $webhookUrl,
        'secret_token'         => $webhookSecret,
        'allowed_updates'      => [
            'message',
            'callback_query',
        ],
        'drop_pending_updates' => false,
        'max_connections'      => 20,
    ]);

    fwrite(
        STDOUT,
        "Webhook configured successfully: {$webhookUrl}\n"
    );

    fwrite(
        STDOUT,
        json_encode(
            $result,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) . PHP_EOL
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Webhook setup failed: {$exception->getMessage()}\n"
    );

    exit(1);
}
