<?php

use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

uses(RefreshDatabase::class);

/**
 * Collect the inline keyboard button labels of the requests made during the
 * last update, optionally limited to one Telegram method.
 *
 * @return array<int, string>
 */
function menuButtons(FakeNutgram $bot, ?string $method = null): array
{
    $buttons = [];

    foreach ($bot->getRequestHistory() as $entry) {
        $request = array_values($entry)[0];

        if ($method !== null && $request->getUri()->getPath() !== $method) {
            continue;
        }

        $markup = FakeNutgram::getActualData($request)['reply_markup'] ?? null;

        if (is_string($markup)) {
            $markup = json_decode($markup, true, 512, JSON_THROW_ON_ERROR);
        }

        foreach (($markup['inline_keyboard'] ?? []) as $row) {
            foreach ($row as $button) {
                $buttons[] = (string) ($button['text'] ?? '');
            }
        }
    }

    return $buttons;
}

test('a user can subscribe to daily price alerts', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 1000, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 1000, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('item:price_eur')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearCallbackQueryData('time:18:00')->reply();

    $alert = PriceAlert::query()->first();

    expect($alert)
        ->not->toBeNull()
        ->and($alert->chat_id)->toBe(1000)
        ->and($alert->notify_time)->toBe('18:00')
        ->and($alert->items)->toBe(['price_dollar_rl', 'price_eur']);
});

test('a user can subscribe with a custom time', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 2000, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 2000, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:crypto-bitcoin-irr')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearCallbackQueryData('custom')->reply();
    $bot->hearText('۰۹:۰۵')->reply();

    $alert = PriceAlert::query()->first();

    expect($alert)
        ->not->toBeNull()
        ->and($alert->notify_time)->toBe('09:05')
        ->and($alert->items)->toBe(['crypto-bitcoin-irr']);
});

test('items can be selected with the reply keyboard while setting up an alert', function () {
    Http::fake();

    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 3000, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 3000, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearText('💵 دلار')->reply();
    $bot->hearText('₮ تتر')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearCallbackQueryData('time:20:00')->reply();

    Http::assertNothingSent();

    $alert = PriceAlert::query()->first();

    expect($alert)
        ->not->toBeNull()
        ->and($alert->notify_time)->toBe('20:00')
        ->and($alert->items)->toBe(['price_dollar_rl', 'crypto-tether-irr']);
});

test('the item selection message ships the continue and cancel buttons', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4000, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4000, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();

    $buttons = menuButtons($bot, 'sendMessage');

    expect($buttons)
        ->toContain('➡️ ادامه')
        ->toContain('❌ لغو')
        ->toContain('▫️ 💵 دلار');
});

test('every item row keeps the continue button after a selection', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4001, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4001, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();

    $buttons = menuButtons($bot, 'editMessageText');

    expect($buttons)
        ->toContain('✅ 💵 دلار')
        ->toContain('➡️ ادامه');
});

test('the continue button opens the time selection with the preset hours', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4002, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4002, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_eur')->reply();
    $bot->hearCallbackQueryData('done')->reply();

    $buttons = menuButtons($bot, 'editMessageText');

    expect($buttons)
        ->toContain('18:00')
        ->toContain('✍️ ساعت دلخواه')
        ->toContain('⬅️ بازگشت');
});

test('the existing alert summary shows the manage buttons', function () {
    PriceAlert::query()->create([
        'chat_id' => 4003,
        'telegram_user_id' => 4003,
        'first_name' => 'Test',
        'notify_time' => '18:00',
        'timezone' => 'Asia/Tehran',
        'items' => ['price_dollar_rl'],
        'is_active' => true,
    ]);

    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4003, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4003, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();

    $buttons = menuButtons($bot, 'sendMessage');

    expect($buttons)
        ->toContain('✏️ تغییر تنظیمات')
        ->toContain('🔕 لغو اعلان');
});

test('tapping a price button while picking the time goes back to the item list', function () {
    Http::fake();

    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4004, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4004, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearText('💶 یورو')->reply();

    $buttons = menuButtons($bot);

    expect($buttons)
        ->toContain('✅ 💵 دلار')
        ->toContain('✅ 💶 یورو')
        ->toContain('➡️ ادامه')
        ->and(PriceAlert::query()->exists())->toBeFalse();
});

test('typing an unknown word at the time step does not save a broken alert', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4005, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4005, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearText('ساعت ۱۸ و نیم')->reply();

    expect(PriceAlert::query()->exists())->toBeFalse();
});

test('the flow can be completed by typing the item names instead of tapping them', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4006, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4006, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearText('💵 دلار')->reply();
    $bot->hearText('ادامه')->reply();

    $buttons = menuButtons($bot);

    expect($buttons)->toContain('18:00');
});

test('the notify command restarts the flow from the time step', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4007, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4007, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearText('/notify')->reply();

    $buttons = menuButtons($bot);

    expect($buttons)
        ->toContain('➡️ ادامه')
        ->toContain('✅ 💵 دلار');

    // The flow is usable again right after the restart.
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearCallbackQueryData('time:18:00')->reply();

    expect(PriceAlert::query()->first()?->notify_time)->toBe('18:00');
});

test('tapping the notify button during setup keeps the selected items', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4008, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4008, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_eur')->reply();
    $bot->hearText('🔔 بهم اطلاع بده')->reply();

    $buttons = menuButtons($bot);

    expect($buttons)
        ->toContain('✅ 💶 یورو')
        ->toContain('➡️ ادامه')
        ->and(PriceAlert::query()->exists())->toBeFalse();
});

test('the restart reuses the same menu message instead of sending a new one', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4009, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4009, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearText('/notify')->reply();

    expect(menuButtons($bot, 'sendMessage'))->toBe([])
        ->and(menuButtons($bot, 'editMessageText'))->toContain('✅ 💵 دلار');
});

test('the notify command starts the setup when no conversation is running', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4010, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4010, type: ChatType::PRIVATE));

    $bot->hearText('/notify')->reply();

    expect(menuButtons($bot, 'sendMessage'))
        ->toContain('➡️ ادامه')
        ->toContain('❌ لغو');
});

test('the start command leaves the setup and shows the main menu', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4011, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4011, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearText('/start')->reply();

    $texts = [];

    foreach ($bot->getRequestHistory() as $entry) {
        $request = array_values($entry)[0];
        $texts[] = (string) FakeNutgram::getActualData($request)['text'];
    }

    expect(implode("\n", $texts))
        ->toContain('خوش آمدید')
        ->and(PriceAlert::query()->exists())->toBeFalse();
});

test('typing cancel during the time step closes the setup', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4012, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4012, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearText('لغو')->reply();

    expect(PriceAlert::query()->exists())->toBeFalse();
});

test('saving an alert for a time that already passed today does not fire immediately', function () {
    $this->travelTo(Carbon::create(2026, 11, 17, 18, 30, 0, 'Asia/Tehran'));

    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4014, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4014, type: ChatType::PRIVATE));

    $bot->hearText('🔔 بهم اطلاع بده')->reply();
    $bot->hearCallbackQueryData('item:price_dollar_rl')->reply();
    $bot->hearCallbackQueryData('done')->reply();
    $bot->hearCallbackQueryData('time:18:00')->reply();

    $alert = PriceAlert::query()->first();

    expect($alert)
        ->not->toBeNull()
        ->and($alert->notify_time)->toBe('18:00')
        ->and($alert->last_sent_on?->toDateString())->toBe('2026-11-17');
});

test('the start command shows the main menu when no conversation is running', function () {
    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 4013, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 4013, type: ChatType::PRIVATE));

    $bot->hearText('/start')->reply();

    $texts = [];
    $replyButtons = [];

    foreach ($bot->getRequestHistory() as $entry) {
        $request = array_values($entry)[0];
        $data = FakeNutgram::getActualData($request);
        $texts[] = (string) ($data['text'] ?? '');

        $markup = $data['reply_markup'] ?? null;

        if (is_string($markup)) {
            $markup = json_decode($markup, true, 512, JSON_THROW_ON_ERROR);
        }

        foreach (($markup['keyboard'] ?? []) as $row) {
            foreach ($row as $button) {
                $replyButtons[] = (string) ($button['text'] ?? '');
            }
        }
    }

    expect(implode("\n", $texts))->toContain('خوش آمدید')
        ->and($replyButtons)
        ->toContain('💰 بررسی قیمت‌ها')
        ->toContain('🔔 بهم اطلاع بده');
});
