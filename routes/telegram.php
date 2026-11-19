<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Commands\Welcome\WelcomeCommand;
use App\Telegram\Conversations\PriceAlertConversation;
use App\Telegram\Handlers\Price\ChooseItemPriceHandler;
use App\Telegram\Handlers\Price\ItemPriceHandler;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

$bot->onCommand('start', WelcomeCommand::class)->description('The welcome command!');
$bot->onCommand('notify', PriceAlertConversation::class)->description('Daily price alert subscription');

$bot->onText('🔔 بهم اطلاع بده', PriceAlertConversation::class);
$bot->onText('بهم اطلاع بده', PriceAlertConversation::class);

$bot->onText('بررسی قیمت ها', ChooseItemPriceHandler::class);
$bot->onText('💰 بررسی قیمت‌ها', ChooseItemPriceHandler::class);

// A single catch-all message handler: specific onText handlers above terminate
// any running conversation, so prices are resolved here instead. This way the
// user can also tap the reply keyboard buttons while setting up an alert.
$bot->onMessage(ItemPriceHandler::class);
