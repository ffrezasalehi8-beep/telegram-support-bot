<?php

declare(strict_types=1);

final class SupportBot
{
    public function __construct(
        private readonly Telegram $telegram,
        private readonly string $adminId
    ) {
        if (!preg_match('/^\d{1,20}$/', $this->adminId)) {
            throw new InvalidArgumentException('ADMIN_ID is invalid.');
        }
    }

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        if (!isset($update['message']) || !is_array($update['message'])) {
            return;
        }

        $message = $update['message'];

        $chatType = $message['chat']['type'] ?? '';
        $senderId = isset($message['from']['id'])
            ? (string) $message['from']['id']
            : '';

        // این ربات فقط در گفت‌وگوی خصوصی کار می‌کند.
        if ($chatType !== 'private' || $senderId === '') {
            return;
        }

        if ($senderId === $this->adminId) {
            $this->handleAdminMessage($message);
            return;
        }

        $this->handleUserMessage($message);
    }

    private function handleUserMessage(array $message): void
    {
        $userId    = (string) $message['from']['id'];
        $chatId    = (string) $message['chat']['id'];
        $messageId = (int) $message['message_id'];
        $text      = trim((string) ($message['text'] ?? ''));

        if ($this->isStartCommand($text)) {
            $this->telegram->sendMessage(
                $chatId,
                "🌟 به ربات پشتیبانی خوش آمدید!\n\n" .
                "✉️ پیام، تصویر، فایل، ویدئو یا درخواست خود را ارسال کنید تا به مدیریت منتقل شود."
            );

            return;
        }

        $adminMessageId = $this->deliverMessageToAdmin(
            $chatId,
            $messageId
        );

        if ($adminMessageId === null) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ ارسال پیام با خطا مواجه شد.\nلطفاً کمی بعد دوباره تلاش کنید."
            );

            return;
        }

        $firstName = trim((string) ($message['from']['first_name'] ?? 'بدون نام'));
        $lastName  = trim((string) ($message['from']['last_name'] ?? ''));
        $username  = trim((string) ($message['from']['username'] ?? ''));

        $fullName = trim($firstName . ' ' . $lastName);
        $usernameText = $username !== ''
            ? '@' . $username
            : 'ندارد';

        $infoText =
            "📢 پیام جدید دریافت شد\n\n" .
            "👤 نام: {$fullName}\n" .
            "🔗 نام کاربری: {$usernameText}\n" .
            "🆔 شناسه: {$userId}\n\n" .
            "برای پاسخ، روی پیام فورواردشده ریپلای کنید یا دکمهٔ زیر را بزنید.\n" .
            "#TARGET:{$userId}";

        try {
            $this->telegram->sendMessage(
                $this->adminId,
                $infoText,
                [
                    'reply_parameters' => [
                        'message_id' => $adminMessageId,
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                [
                                    'text'          => '📩 ارسال پاسخ',
                                    'callback_data' => "answer:{$userId}",
                                ],
                            ],
                        ],
                    ],
                ]
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }

        $this->telegram->sendMessage(
            $chatId,
            "📨 پیام شما با موفقیت برای مدیریت ارسال شد.\nلطفاً منتظر پاسخ بمانید."
        );
    }

    private function handleAdminMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text   = trim((string) ($message['text'] ?? ''));

        if ($this->isStartCommand($text)) {
            $this->telegram->sendMessage(
                $chatId,
                "👋 ادمین عزیز، خوش آمدید!\n\n" .
                "برای پاسخ دادن یکی از این دو روش را استفاده کنید:\n\n" .
                "1️⃣ روی پیام فورواردشدهٔ کاربر ریپلای کنید.\n" .
                "2️⃣ دکمهٔ «ارسال پاسخ» زیر اطلاعات کاربر را بزنید."
            );

            return;
        }

        $receiverId = $this->extractReceiverId($message);

        if ($receiverId === null) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر مقصد مشخص نیست.\n\n" .
                "لطفاً روی پیام کاربر ریپلای کنید یا دکمهٔ «ارسال پاسخ» را بزنید."
            );

            return;
        }

        try {
            /*
             * copyMessage تقریباً تمام انواع پیام‌ها را بدون نیاز به
             * نوشتن شرط جداگانه برای عکس، فیلم، فایل، صدا و... منتقل می‌کند.
             */
            $this->telegram->request('copyMessage', [
                'chat_id'      => $receiverId,
                'from_chat_id' => $chatId,
                'message_id'   => (int) $message['message_id'],
            ]);

            $this->telegram->sendMessage(
                $chatId,
                "✅ پاسخ با موفقیت برای کاربر {$receiverId} ارسال شد."
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->telegram->sendMessage(
                $chatId,
                "❌ ارسال پاسخ ناموفق بود.\n" .
                "ممکن است کاربر ربات را مسدود کرده باشد یا نوع پیام قابل کپی نباشد."
            );
        }
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $senderId   = isset($callbackQuery['from']['id'])
            ? (string) $callbackQuery['from']['id']
            : '';

        if ($callbackId === '') {
            return;
        }

        if ($senderId !== $this->adminId) {
            $this->telegram->answerCallbackQuery(
                $callbackId,
                'شما اجازهٔ انجام این عملیات را ندارید.'
            );

            return;
        }

        $callbackData = (string) ($callbackQuery['data'] ?? '');

        if (!preg_match('/^answer:(\d{1,20})$/D', $callbackData, $matches)) {
            $this->telegram->answerCallbackQuery(
                $callbackId,
                'اطلاعات دکمه نامعتبر است.'
            );

            return;
        }

        $receiverId = $matches[1];

        $this->telegram->answerCallbackQuery(
            $callbackId,
            'پیام پاسخ را ارسال کنید.'
        );

        $this->telegram->sendMessage(
            $this->adminId,
            "✍️ پاسخ خود را در جواب همین پیام ارسال کنید.\n\n" .
            "کاربر مقصد: {$receiverId}\n" .
            "#TARGET:{$receiverId}",
            [
                'reply_markup' => [
                    'force_reply' => true,
                    'selective'   => true,
                ],
            ]
        );
    }

    private function deliverMessageToAdmin(
        string $fromChatId,
        int $messageId
    ): ?int {
        try {
            $result = $this->telegram->request('forwardMessage', [
                'chat_id'      => $this->adminId,
                'from_chat_id' => $fromChatId,
                'message_id'   => $messageId,
            ]);

            return isset($result['result']['message_id'])
                ? (int) $result['result']['message_id']
                : null;
        } catch (Throwable $forwardException) {
            error_log($forwardException->getMessage());

            /*
             * اگر فوروارد به‌علت تنظیمات حریم خصوصی کاربر شکست خورد،
             * تلاش می‌کنیم پیام را کپی کنیم.
             */
            try {
                $result = $this->telegram->request('copyMessage', [
                    'chat_id'      => $this->adminId,
                    'from_chat_id' => $fromChatId,
                    'message_id'   => $messageId,
                ]);

                return isset($result['result']['message_id'])
                    ? (int) $result['result']['message_id']
                    : null;
            } catch (Throwable $copyException) {
                error_log($copyException->getMessage());
                return null;
            }
        }
    }

    private function extractReceiverId(array $message): ?string
    {
        $reply = $message['reply_to_message'] ?? null;

        if (!is_array($reply)) {
            return null;
        }

        /*
         * ساختار جدید Bot API برای پیام‌های فورواردشده.
         */
        $forwardOrigin = $reply['forward_origin'] ?? null;

        if (
            is_array($forwardOrigin) &&
            ($forwardOrigin['type'] ?? '') === 'user' &&
            isset($forwardOrigin['sender_user']['id'])
        ) {
            return (string) $forwardOrigin['sender_user']['id'];
        }

        /*
         * پشتیبانی از ساختار قدیمی‌تر تلگرام.
         */
        if (isset($reply['forward_from']['id'])) {
            return (string) $reply['forward_from']['id'];
        }

        /*
         * استخراج شناسه از پیام ساخته‌شده توسط دکمه یا پیام اطلاعات کاربر.
         */
        $replyText = trim(
            (string) ($reply['text'] ?? '') . "\n" .
            (string) ($reply['caption'] ?? '')
        );

        if (preg_match('/#TARGET:(\d{1,20})/', $replyText, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isStartCommand(string $text): bool
    {
        return preg_match(
            '/^\/start(?:@[A-Za-z0-9_]+)?(?:\s|$)/u',
            $text
        ) === 1;
    }
}
