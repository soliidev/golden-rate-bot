<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Commands\Welcome\WelcomeCommand;
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

$bot->onText('بررسی قیمت ها', ChooseItemPriceHandler::class);
$bot->onText('دلار', ItemPriceHandler::class);
$bot->onText('یورو', ItemPriceHandler::class);
$bot->onText('سکه بهار آزادی', ItemPriceHandler::class);
$bot->onText('ربع سکه', ItemPriceHandler::class);
$bot->onText('نیم سکه', ItemPriceHandler::class);
$bot->onText('طلای دست دوم', ItemPriceHandler::class);
$bot->onText('بیت کوین', ItemPriceHandler::class);
$bot->onText('تتر', ItemPriceHandler::class);
$bot->onText('ریپل', ItemPriceHandler::class);
