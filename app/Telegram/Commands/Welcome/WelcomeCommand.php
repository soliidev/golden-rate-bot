<?php

namespace App\Telegram\Commands\Welcome;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class WelcomeCommand extends Command
{
    protected string $command = 'start';

    protected ?string $description = 'The welcome command!';

    public function handle(Nutgram $bot): void
    {
        $bot->sendMessage(
            'به ربات نرخ طلایی خوش آمدید. لطفا یکی از فعالیت های زیر را انتخاب کنید!!',
            reply_markup: ReplyKeyboardMarkup::make(true)
                ->addRow(
                    KeyboardButton::make('بررسی قیمت ها')
                )
        );
    }
}
