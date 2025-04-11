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

        $priceInfo = $this->getItemPrice($message);

        $price = $priceInfo['p'];
        $minPrice = $priceInfo['l'];
        $maxPrice = $priceInfo['h'];

        $price = $this->convertToToman($price);
        $minPrice = $this->convertToToman($minPrice);
        $maxPrice = $this->convertToToman($maxPrice);

        $bot->sendMessage(
            "قیمت فعلی $message: $price تومان \n" .
            "کمترین قیمت: $minPrice تومان \n" .
            " بیشترین قیمت: $maxPrice تومان \n"
        );
    }

    /**
     * @throws RequestException
     */
    public function getItemPrice($item)
    {
        $itemPrice = (new CurrentItemPriceService())->getCurrentItemPrice();

        $itemPriceKey = $this->getItemPriceKey($item);

        return $itemPrice[$itemPriceKey];
    }

    public function getItemPriceKey($item): string
    {
        return match ($item) {
            'دلار' => 'price_dollar_rl',
            'یورو' => 'price_eur',
            'سکه بهار آزادی' => 'sekee',
            'ربع سکه' => 'rob',
            'نیم سکه' => 'nim',
            'طلای دست دوم' => 'gold_mini_size',
            'بیت کوین' => 'crypto-bitcoin-irr',
            'تتر' => 'crypto-tether-irr',
            'ریپل' => 'crypto-ripple-irr',
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
