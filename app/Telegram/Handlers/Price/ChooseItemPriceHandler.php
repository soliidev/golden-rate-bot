<?php

namespace App\Telegram\Handlers\Price;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class ChooseItemPriceHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            'جهت مشاهده قیمت، یکی از گزینه‌های زیر را انتخاب کنید:',
            reply_markup: ReplyKeyboardMarkup::make(true)
                ->addRow(
                    KeyboardButton::make('💵 دلار'),
                    KeyboardButton::make('💶 یورو')
                )
                ->addRow(
                    KeyboardButton::make('🪙 سکه بهار آزادی'),
                    KeyboardButton::make('🪙 ربع سکه'),
                    KeyboardButton::make('🪙 نیم سکه'),
                    KeyboardButton::make('🥇 طلای دست دوم')
                )
                ->addRow(
                    KeyboardButton::make('₿ بیت کوین'),
                    KeyboardButton::make('₮ تتر'),
                    KeyboardButton::make('✳️ ریپل')
                )
                ->addRow(
                    KeyboardButton::make('🔔 بهم اطلاع بده')
                )
        );
    }
}
