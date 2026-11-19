<?php

use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

test('tapping a price reply button returns the current price', function () {
    Http::fake([
        '*' => Http::response([
            'current' => [
                'price_dollar_rl' => ['p' => '2,669,000', 'l' => '2,666,600', 'h' => '2,699,000'],
            ],
        ]),
    ]);

    $bot = app(Nutgram::class);

    $bot->setCommonUser(User::make(id: 7000, is_bot: false, first_name: 'Test'));
    $bot->setCommonChat(Chat::make(id: 7000, type: ChatType::PRIVATE));

    $bot->hearText('💵 دلار')->reply();

    $history = $bot->getRequestHistory();

    $bot->assertCalled('sendMessage', 2);

    $priceReply = FakeNutgram::getActualData(array_values($history[1])[0]);

    expect($priceReply['text'])->toContain('266,900');
});
