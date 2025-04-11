<?php

namespace App\Telegram\Handlers\Price;

use App\Services\CurrentItemPrice\CurrentItemPriceService;
use Illuminate\Http\Client\RequestException;
use SergiX44\Nutgram\Nutgram;

class ItemPriceHandler
{
    /**
     * @throws RequestException
     */
    public function __invoke(Nutgram $bot): void
    {
        $message = $bot->message()->text;

        $bot->sendMessage("در حال گرفتن قیمت $message ...");

        $price = $this->getItemPrice($message);
        $price = $this->convertToToman($price);

        $bot->sendMessage("قیمت فعلی $message: $price تومان");
    }

    /**
     * @throws RequestException
     */
    public function getItemPrice($item)
    {
        $itemPrice = (new CurrentItemPriceService())->getCurrentItemPrice();

        $itemPriceKey = $this->getItemPriceKey($item);

        return $itemPrice[$itemPriceKey]['p'];
    }

    public function getItemPriceKey($item): string
    {
        return match ($item) {
            'دلار' => 'price_dollar_rl',
            'سکه بهار آزادی' => 'sekee',
            'ربع سکه' => 'rob',
            'نیم سکه' => 'nim',
            'بیت کوین' => 'aa',
            default => '',
        };
    }

    public function convertToToman($price): string
    {
        $price = str_replace(',', '', $price);
        $price = (int)$price / 10;

        return number_format($price);
    }
}
