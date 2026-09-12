<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

/*
 * Health Check مخصوص Railway
 */
if ($method === 'GET' && $path === '/health') {
    http_response_code(200);

    echo json_encode([
        'status' => 'healthy',
        'time'   => gmdate('c'),
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * صفحه اصلی
 */
if ($method === 'GET' && $path === '/') {
    http_response_code(200);

    echo json_encode([
        'status'  => 'online',
        'service' => 'Telegram Support Bot',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * تنها آدرس /webhook باید درخواست POST تلگرام را دریافت کند.
 */
if ($method !== 'POST' || $path !== '/webhook') {
    http_response_code(404);

    echo json_encode([
        'status' => 'not_found',
    ]);

    exit;
}

$botToken      = trim((string) getenv('BOT_TOKEN'));
$adminId       = trim((string) getenv('ADMIN_ID'));
$webhookSecret = trim((string) getenv('WEBHOOK_SECRET'));

if (
    $botToken === '' ||
    $adminId === '' ||
    $webhookSecret === ''
) {
    error_log('Required environment variables are missing.');

    /*
     * پاسخ 200 مانع تکرار بی‌پایان یک آپدیت توسط تلگرام می‌شود.
     */
    http_response_code(200);

    echo json_encode([
        'status' => 'configuration_error',
    ]);

    exit;
}

/*
 * اعتبارسنجی هدر امنیتی ارسال‌شده توسط Telegram.
 */
$receivedSecret = (string) (
    $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''
);

if (
    $receivedSecret === '' ||
    !hash_equals($webhookSecret, $receivedSecret)
) {
    http_response_code(403);

    echo json_encode([
        'status' => 'forbidden',
    ]);

    exit;
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    http_response_code(400);

    echo json_encode([
        'status' => 'empty_request',
    ]);

    exit;
}

try {
    $update = json_decode(
        $rawBody,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($update)) {
        throw new RuntimeException('Update is not an array.');
    }

    require_once dirname(__DIR__) . '/src/Telegram.php';
    require_once dirname(__DIR__) . '/src/SupportBot.php';

    $telegram = new Telegram($botToken);
    $bot      = new SupportBot($telegram, $adminId);

    $bot->handle($update);

    http_response_code(200);

    echo json_encode([
        'status' => 'ok',
    ]);
} catch (Throwable $exception) {
    error_log(
        sprintf(
            '[%s] %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    /*
     * برای جلوگیری از ارسال دوباره و چندبارهٔ همان پیام توسط تلگرام،
     * در خطاهای داخلی نیز پاسخ 200 ارسال می‌کنیم و خطا در Logs ثبت می‌شود.
     */
    http_response_code(200);

    echo json_encode([
        'status' => 'handled_with_error',
    ]);
}
