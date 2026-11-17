<?php

use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;

uses(RefreshDatabase::class);

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
