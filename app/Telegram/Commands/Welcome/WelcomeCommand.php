<?php

namespace App\Telegram\Commands\Welcome;

use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class WelcomeCommand extends Command
{
    protected string $command = 'start';

    protected ?string $description = 'The welcome command!';

    public function handle(Nutgram $bot): void
    {
        $bot->sendMessage(
            'به ربات نرخ طلایی خوش آمدید! 🎉
لطفا برای دریافت قیمت روی دکمه زیر بزنید.',
            reply_markup: self::keyboard()
        );
    }

    /**
     * The main reply keyboard shown to the user.
     */
    public static function keyboard(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(true)
            ->addRow(
                KeyboardButton::make('💰 بررسی قیمت‌ها'),
                KeyboardButton::make('🔔 بهم اطلاع بده')
            );
    }
}
