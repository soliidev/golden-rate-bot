<?php

namespace App\Telegram\Conversations;

use App\Models\PriceAlert;
use App\Support\PriceItems;
use App\Telegram\Commands\Welcome\WelcomeCommand;
use Illuminate\Database\Eloquent\Collection;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Lets the user pick the prices they care about and the time of day at
 * which the bot should send them those prices automatically.
 */
class PriceAlertConversation extends Conversation
{
    /**
     * Item keys currently selected by the user.
     *
     * @var array<int, string>
     */
    protected array $selected = [];

    /**
     * The time currently stored for the user, if any (HH:MM).
     */
    protected ?string $time = null;

    /**
     * The notify_time of the alert being edited, if any. Saving with a
     * different time then replaces that row instead of adding a new one.
     */
    protected ?string $editingTime = null;

    protected ?int $menuChatId = null;

    protected ?int $menuMessageId = null;

    public function __construct()
    {
        // Keep this conversation alive for every update. Without it any
        // matching onText/onCommand handler (like the notify button) would
        // terminate the conversation and silently restart it from scratch.
        $this->setSkipHandlers(true);
    }

    public function start(Nutgram $bot): void
    {
        if ($this->currentAlerts($bot)->isNotEmpty()) {
            $this->showList($bot);
            $this->next('handleList');

            return;
        }

        $this->showItems($bot);
        $this->next('handleItems');
    }

    /**
     * The list of the saved alerts: add, edit or delete them from here.
     */
    public function handleList(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            if ($this->handleRestartRequest($bot)) {
                return;
            }

            $text = trim((string) $bot->message()?->text);

            if (in_array($text, ['لغو', 'انصراف', 'cancel'], true)) {
                $bot->sendMessage('❌ عملیات لغو شد.');
                $this->end();

                return;
            }

            $key = PriceItems::fromText($text);

            if ($key !== null) {
                // Typing a price name starts a new alert for that price.
                $this->selected = [$key];
                $this->time = null;
                $this->editingTime = null;
                $this->showItems($bot);
                $this->next('handleItems');

                return;
            }

            $bot->sendMessage('از دکمه‌های زیر استفاده کنید: «➕ افزودن ساعت جدید»، ✏️ برای تغییر و 🗑 برای حذف.');

            return;
        }

        $data = (string) $bot->callbackQuery()?->data;

        if ($data === 'add') {
            $bot->answerCallbackQuery();
            $this->selected = [];
            $this->time = null;
            $this->editingTime = null;
            $this->showItems($bot);
            $this->next('handleItems');

            return;
        }

        if (str_starts_with($data, 'edit:')) {
            $bot->answerCallbackQuery();
            $alert = $this->alertAt($bot, substr($data, 5));

            if ($alert === null) {
                $this->showList($bot);
                $this->next('handleList');

                return;
            }

            $this->selected = array_values(array_filter(
                (array) $alert->items,
                static fn (string $key): bool => PriceItems::exists($key)
            ));
            $this->time = $alert->notify_time;
            $this->editingTime = $alert->notify_time;
            $this->showItems($bot);
            $this->next('handleItems');

            return;
        }

        if (str_starts_with($data, 'del:')) {
            $bot->answerCallbackQuery();
            $time = substr($data, 4);
            $this->deleteAlert($bot, $time);

            if ($this->currentAlerts($bot)->isEmpty()) {
                $this->selected = [];
                $this->time = null;
                $this->editingTime = null;
                $this->render($bot, "🗑 اعلان ساعت $time حذف شد.\n\nهیچ اعلانی ثبت نیست. هر زمان خواستید روی «🔔 بهم اطلاع بده» بزنید.");
                $this->end();

                return;
            }

            $this->showList($bot, "🗑 اعلان ساعت $time حذف شد.");
            $this->next('handleList');

            return;
        }

        if ($data === 'delete_all') {
            $bot->answerCallbackQuery();
            PriceAlert::query()->where('chat_id', $bot->chatId())->delete();
            $this->selected = [];
            $this->time = null;
            $this->editingTime = null;
            $this->render($bot, "🔕 همه اعلان‌های روزانه شما لغو شد.\n\nهر زمان بخواهید می‌توانید دوباره روی «🔔 بهم اطلاع بده» بزنید.");
            $this->end();

            return;
        }

        if ($data === 'close') {
            $bot->answerCallbackQuery();
            $this->render($bot, 'از دکمه‌های زیر می‌توانید قیمت‌ها را ببینید یا اعلان روزانه بسازید.');
            $this->end();

            return;
        }

        $bot->answerCallbackQuery();
    }

    public function handleItems(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            $text = $bot->message()?->text;

            if ($this->handleRestartRequest($bot)) {
                return;
            }

            if ($this->switchToItemsFromText($bot)) {
                return;
            }

            if (in_array(trim((string) $text), ['ادامه', 'تایید', 'ثبت', '✅ ادامه'], true)) {
                $this->continueToTime($bot);

                return;
            }

            $bot->sendMessage(
                'برای انتخاب، روی دکمه‌های همین پیام بزنید یا نام قیمت را بنویسید.'."\n".
                'برای شروع دوباره «🔔 بهم اطلاع بده» و برای خروج «/start» را بزنید.'
            );

            return;
        }

        $data = (string) $bot->callbackQuery()?->data;

        if (str_starts_with($data, 'item:')) {
            $this->toggleItem(substr($data, 5));
            $bot->answerCallbackQuery();
            $this->showItems($bot);
            $this->next('handleItems');

            return;
        }

        if ($data === 'done') {
            $this->continueToTime($bot);

            return;
        }

        if ($data === 'cancel') {
            $bot->answerCallbackQuery();
            $this->cancelFlow($bot);

            return;
        }

        $bot->answerCallbackQuery();
    }

    /**
     * Leaving the setup: go back to the alert list when there is one,
     * otherwise just close the conversation.
     */
    protected function cancelFlow(Nutgram $bot): void
    {
        $this->editingTime = null;
        $this->time = null;
        $this->selected = [];

        if ($this->currentAlerts($bot)->isNotEmpty()) {
            $this->showList($bot, '❌ عملیات لغو شد.');
            $this->next('handleList');

            return;
        }

        $this->render($bot, '❌ عملیات لغو شد.');
        $this->end();
    }

    protected function toggleItem(?string $key): bool
    {
        if ($key === null || ! PriceItems::exists($key)) {
            return false;
        }

        if (in_array($key, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$key]));
        } else {
            $this->selected[] = $key;
        }

        return true;
    }

    /**
     * When the user taps a price on the reply keyboard while another step
     * is open, go back to the item selection with that price toggled.
     */
    protected function switchToItemsFromText(Nutgram $bot): bool
    {
        if (! $this->toggleItem(PriceItems::fromText($bot->message()?->text))) {
            return false;
        }

        $this->showItems($bot);
        $this->next('handleItems');

        return true;
    }

    protected function continueToTime(Nutgram $bot): void
    {
        if ($this->selected === []) {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery(text: 'حداقل یک قیمت را انتخاب کنید.', show_alert: true);
            } else {
                $bot->sendMessage('حداقل یک قیمت را انتخاب کنید.');
            }

            return;
        }

        if ($bot->isCallbackQuery()) {
            $bot->answerCallbackQuery();
        }

        $this->showTimes($bot);
        $this->next('handleTime');
    }

    public function handleTime(Nutgram $bot): void
    {
        if (! $bot->isCallbackQuery()) {
            if ($this->handleRestartRequest($bot)) {
                return;
            }

            if ($this->switchToItemsFromText($bot)) {
                return;
            }

            $this->saveFromText($bot, $bot->message()?->text);

            return;
        }

        $data = (string) $bot->callbackQuery()?->data;

        if ($data === 'custom') {
            $bot->answerCallbackQuery();
            $bot->sendMessage('🕒 ساعت مورد نظر را به صورت ۲۴ ساعته و در قالب HH:MM وارد کنید. مثال: 18:30');
            $this->next('handleCustomTime');

            return;
        }

        if ($data === 'back') {
            $bot->answerCallbackQuery();
            $this->showItems($bot);
            $this->next('handleItems');

            return;
        }

        if ($data === 'cancel') {
            $bot->answerCallbackQuery();
            $this->cancelFlow($bot);

            return;
        }

        if (str_starts_with($data, 'time:')) {
            $bot->answerCallbackQuery();
            $this->save($bot, substr($data, 5));

            return;
        }

        $bot->answerCallbackQuery();
    }

    public function handleCustomTime(Nutgram $bot): void
    {
        if ($this->handleRestartRequest($bot)) {
            return;
        }

        if ($this->switchToItemsFromText($bot)) {
            return;
        }

        $this->saveFromText($bot, $bot->message()?->text);
    }

    /**
     * The alert setup can be restarted from any step with the notify button
     * or the /notify command. /start leaves the setup and shows the menu.
     */
    protected function handleRestartRequest(Nutgram $bot): bool
    {
        if ($bot->isCallbackQuery()) {
            return false;
        }

        $text = trim((string) $bot->message()?->text);

        if (str_starts_with($text, '/start')) {
            $bot->sendMessage(
                "به ربات نرخ طلایی خوش آمدید! 🎉\nلطفا برای دریافت قیمت روی دکمه زیر بزنید.",
                reply_markup: WelcomeCommand::keyboard(),
            );
            $this->end();

            return true;
        }

        if (
            in_array($text, ['🔔 بهم اطلاع بده', 'بهم اطلاع بده'], true)
            || str_starts_with($text, '/notify')
        ) {
            $this->start($bot);

            return true;
        }

        return false;
    }

    protected function saveFromText(Nutgram $bot, ?string $text): void
    {
        if (in_array(trim((string) $text), ['لغو', 'انصراف', 'cancel'], true)) {
            $this->cancelFlow($bot);

            return;
        }

        $time = $this->parseTime($text);

        if ($time === null) {
            $bot->sendMessage(
                "❌ ساعت وارد شده معتبر نیست.\n".
                'لطفاً ساعت را به صورت HH:MM و بین 00:00 تا 23:59 وارد کنید. مثال: 18:30'
            );

            return;
        }

        $this->save($bot, $time);
    }

    protected function save(Nutgram $bot, string $time): void
    {
        $chatId = $bot->chatId();

        if ($chatId === null) {
            $this->end();

            return;
        }

        $items = array_values(array_filter(
            $this->selected,
            static fn (string $key): bool => PriceItems::exists($key)
        ));

        if ($items === []) {
            $bot->sendMessage('❌ هیچ قیمتی انتخاب نشده است. لطفاً از دکمه «🔔 بهم اطلاع بده» دوباره شروع کنید.');
            $this->end();

            return;
        }

        $user = $bot->user();

        // If the chosen time already passed today, mark it as sent so the
        // scheduler does not fire it right after saving; it starts tomorrow.
        $now = now(config('price-alerts.timezone', 'Asia/Tehran'));
        $alreadyPassed = $time <= $now->format('H:i');

        // Editing with a different time replaces the old row instead of
        // leaving a duplicate behind.
        if ($this->editingTime !== null && $this->editingTime !== $time) {
            $this->deleteAlert($bot, $this->editingTime);
        }

        $this->editingTime = null;
        $this->time = $time;

        // One row per (chat, time): the same time updates its own alert,
        // a new time adds another one for the same day.
        PriceAlert::query()->updateOrCreate(
            ['chat_id' => $chatId, 'notify_time' => $time],
            [
                'telegram_user_id' => $bot->userId(),
                'username' => $user?->username,
                'first_name' => $user?->first_name,
                'timezone' => config('price-alerts.timezone', 'Asia/Tehran'),
                'items' => $items,
                'is_active' => true,
                'last_sent_on' => $alreadyPassed ? $now->toDateString() : null,
            ]
        );

        $this->showList(
            $bot,
            "✅ اعلان ساعت $time ثبت شد.\n\n".
            '📊 '.PriceItems::labels($items)."\n".
            ($alreadyPassed
                ? "چون ساعت $time امروز گذشته است، از فردا برای شما ارسال می‌شود.\n"
                : "از این پس قیمت‌های لحظه‌ای در همین ساعت برای شما ارسال می‌شود.\n").
            "\nبرای افزودن ساعت دیگر، «➕ افزودن ساعت جدید» را بزنید."
        );
        $this->next('handleList');
    }

    protected function showItems(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach (PriceItems::all() as $key => $item) {
            $isSelected = in_array($key, $this->selected, true);

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    ($isSelected ? '✅ ' : '▫️ ').$item['emoji'].' '.$item['label'],
                    callback_data: 'item:'.$key
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('➡️ ادامه', callback_data: 'done'),
            InlineKeyboardButton::make('❌ لغو', callback_data: 'cancel'),
        );

        $this->render(
            $bot,
            "🔔 اعلان قیمت روزانه\n\n".
            "قیمت‌هایی که می‌خواهید هر روز برایتان ارسال شود را انتخاب کنید.\n".
            "روی هر گزینه بزنید تا انتخاب یا لغو شود.\n\n".
            'انتخاب‌شده: '.count($this->selected).' از '.count(PriceItems::all()),
            $keyboard
        );
    }

    protected function showTimes(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ((array) config('price-alerts.preset_times', []) as $preset) {
            $label = ($this->time === $preset ? '✅ ' : '').$preset;
            $row[] = InlineKeyboardButton::make($label, callback_data: 'time:'.$preset);

            if (count($row) === 3) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if ($row !== []) {
            $keyboard->addRow(...$row);
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('✍️ ساعت دلخواه', callback_data: 'custom'),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make('⬅️ بازگشت', callback_data: 'back'),
            InlineKeyboardButton::make('❌ لغو', callback_data: 'cancel'),
        );

        $this->render(
            $bot,
            "🕒 ساعت ارسال را انتخاب کنید.\n\n".
            "قیمت‌های انتخاب‌شده هر روز در این ساعت برای شما ارسال می‌شود.\n".
            'برای ساعت دلخواه، روی «✍️ ساعت دلخواه» بزنید و ساعت را تایپ کنید.',
            $keyboard
        );
    }

    /**
     * The list of every saved alert with its own edit/delete buttons.
     */
    protected function showList(Nutgram $bot, ?string $notice = null): void
    {
        $alerts = $this->currentAlerts($bot);

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($alerts as $alert) {
            $keyboard->addRow(
                InlineKeyboardButton::make('✏️ تغییر '.$alert->notify_time, callback_data: 'edit:'.$alert->notify_time),
                InlineKeyboardButton::make('🗑 حذف '.$alert->notify_time, callback_data: 'del:'.$alert->notify_time),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('➕ افزودن ساعت جدید', callback_data: 'add'),
        );

        if ($alerts->count() > 1) {
            $keyboard->addRow(
                InlineKeyboardButton::make('🔕 لغو همه اعلان‌ها', callback_data: 'delete_all'),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('✖️ بستن', callback_data: 'close'),
        );

        $lines = [];

        foreach ($alerts as $index => $alert) {
            $lines[] = ($index + 1).') 🕒 '.$alert->notify_time.' — '.PriceItems::labels((array) $alert->items);
        }

        $text = ($notice !== null ? $notice."\n\n" : '')
            .'🔔 اعلان‌های روزانه شما ('.count($alerts)."):\n\n"
            .implode("\n", $lines)."\n\n"
            .'برای افزودن ساعت دیگر، تغییر یا حذف از دکمه‌های زیر استفاده کنید.';

        $this->render($bot, $text, $keyboard);
    }

    /**
     * Replace the current menu message, or send a new one when there is
     * no menu to edit (or editing is not possible anymore).
     */
    protected function render(Nutgram $bot, string $text, ?InlineKeyboardMarkup $keyboard = null): void
    {
        $keyboard ??= InlineKeyboardMarkup::make();

        if ($bot->isCallbackQuery()) {
            $message = $bot->callbackQuery()?->message;

            if ($message !== null) {
                $this->menuChatId = $message->chat?->id;
                $this->menuMessageId = $message->message_id;

                try {
                    $bot->editMessageText(
                        text: $text,
                        chat_id: $this->menuChatId,
                        message_id: $this->menuMessageId,
                        reply_markup: $keyboard,
                    );

                    return;
                } catch (TelegramException $exception) {
                    if ($this->isNotModified($exception)) {
                        return;
                    }
                    // The message cannot be edited anymore, send a new one.
                }
            }
        } elseif ($this->menuChatId !== null && $this->menuMessageId !== null) {
            try {
                $bot->editMessageText(
                    text: $text,
                    chat_id: $this->menuChatId,
                    message_id: $this->menuMessageId,
                    reply_markup: $keyboard,
                );

                return;
            } catch (TelegramException $exception) {
                if ($this->isNotModified($exception)) {
                    return;
                }
                // The message cannot be edited anymore, send a new one.
            }
        }

        $message = $bot->sendMessage(text: $text, reply_markup: $keyboard);

        $this->menuChatId = $message?->chat?->id;
        $this->menuMessageId = $message?->message_id;
    }

    protected function isNotModified(TelegramException $exception): bool
    {
        return str_contains($exception->getMessage(), 'not modified');
    }

    protected function parseTime(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = PriceItems::normalizeDigits(trim($text));
        $text = str_replace(['.', '٫', '،', ' '], [':', ':', ':', ''], $text);

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $text, $matches)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    /**
     * Every alert saved for this chat, oldest time first.
     *
     * @return Collection<int, PriceAlert>
     */
    protected function currentAlerts(Nutgram $bot)
    {
        return PriceAlert::query()
            ->where('chat_id', $bot->chatId())
            ->orderBy('notify_time')
            ->get();
    }

    protected function alertAt(Nutgram $bot, string $time): ?PriceAlert
    {
        return PriceAlert::query()
            ->where('chat_id', $bot->chatId())
            ->where('notify_time', $time)
            ->first();
    }

    protected function deleteAlert(Nutgram $bot, string $time): void
    {
        PriceAlert::query()
            ->where('chat_id', $bot->chatId())
            ->where('notify_time', $time)
            ->delete();
    }
}
