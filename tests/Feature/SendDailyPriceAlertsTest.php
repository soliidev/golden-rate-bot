<?php

use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;

uses(RefreshDatabase::class);

test('the daily alert command sends the prices of the current minute once a day', function () {
    Http::fake([
        '*' => Http::response([
            'current' => [
                'price_dollar_rl' => ['p' => '2,669,000', 'l' => '2,666,600', 'h' => '2,699,000', 'dp' => 0.68],
                'price_eur' => ['p' => '2,944,400', 'l' => '2,900,000', 'h' => '2,970,000', 'dp' => -0.12],
            ],
        ]),
    ]);

    $this->travelTo(Carbon::create(2026, 11, 17, 18, 0, 0, 'Asia/Tehran'));

    $alert = PriceAlert::query()->create([
        'chat_id' => 5000,
        'telegram_user_id' => 5000,
        'first_name' => 'Test',
        'notify_time' => '18:00',
        'timezone' => 'Asia/Tehran',
        'items' => ['price_dollar_rl', 'price_eur'],
        'is_active' => true,
    ]);

    $this->artisan('alerts:send-daily')->assertSuccessful();

    app(Nutgram::class)->assertCalled('sendMessage', 1);

    expect($alert->fresh()->last_sent_on?->toDateString())->toBe('2026-11-17');

    // Running it again in the same minute must not send the message twice.
    $this->artisan('alerts:send-daily')->assertSuccessful();

    app(Nutgram::class)->assertCalled('sendMessage', 1);
});

test('the daily alert command ignores alerts scheduled for another time', function () {
    Http::fake();

    $this->travelTo(Carbon::create(2026, 11, 17, 18, 0, 0, 'Asia/Tehran'));

    PriceAlert::query()->create([
        'chat_id' => 6000,
        'notify_time' => '19:00',
        'timezone' => 'Asia/Tehran',
        'items' => ['price_dollar_rl'],
        'is_active' => true,
    ]);

    $this->artisan('alerts:send-daily')->assertSuccessful();

    Http::assertNothingSent();
    app(Nutgram::class)->assertCalled('sendMessage', 0);
});

test('a missed minute is caught up on the next run instead of being dropped', function () {
    Http::fake([
        '*' => Http::response([
            'current' => [
                'price_dollar_rl' => ['p' => '2,669,000', 'l' => '2,666,600', 'h' => '2,699,000', 'dp' => 0.68],
            ],
        ]),
    ]);

    // The alert was scheduled for 12:25 but the scheduler only runs at 12:31.
    $this->travelTo(Carbon::create(2026, 11, 17, 12, 31, 0, 'Asia/Tehran'));

    $alert = PriceAlert::query()->create([
        'chat_id' => 7000,
        'telegram_user_id' => 7000,
        'first_name' => 'Test',
        'notify_time' => '12:25',
        'timezone' => 'Asia/Tehran',
        'items' => ['price_dollar_rl'],
        'is_active' => true,
    ]);

    $this->artisan('alerts:send-daily')->assertSuccessful();

    app(Nutgram::class)->assertCalled('sendMessage', 1);
    expect($alert->fresh()->last_sent_on?->toDateString())->toBe('2026-11-17');
});
