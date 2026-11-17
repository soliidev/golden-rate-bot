<?php

namespace App\Telegram\Handlers\Price;

use App\Services\CurrentItemPrice\CurrentItemPriceService;
use App\Support\PriceItems;
use Illuminate\Http\Client\RequestException;
use SergiX44\Nutgram\Nutgram;

class ItemPriceHandler
{
    /**
     * @throws RequestException
     */
    public function __invoke(Nutgram $bot): void
    {
        $message = $bot->message()?->text;

        if ($this->getItemPriceKey($message) === '') {
            return;
        }

        $bot->sendMessage("در حال گرفتن قیمت $message ...");

        $priceInfo = $this->getItemPrice($message);

        if ($priceInfo === null) {
            $bot->sendMessage("قیمت $message در حال حاضر در دسترس نیست.");

            return;
        }

        $price = $this->convertToToman($priceInfo['p'] ?? 0);
        $minPrice = $this->convertToToman($priceInfo['l'] ?? 0);
        $maxPrice = $this->convertToToman($priceInfo['h'] ?? 0);

        $bot->sendMessage(
            "قیمت فعلی $message: $price تومان \n".
                "کمترین قیمت: $minPrice تومان \n".
                " بیشترین قیمت: $maxPrice تومان \n"
        );
    }

    /**
     * @throws RequestException
     */
    public function getItemPrice($item): ?array
    {
        $itemPrice = (new CurrentItemPriceService)->getCurrentItemPrice();

        $itemPriceKey = $this->getItemPriceKey($item);

        return $itemPrice[$itemPriceKey] ?? null;
    }

    public function getItemPriceKey($item): string
    {
        return PriceItems::fromText($item) ?? '';
    }

    public function convertToToman($price): string
    {
        return PriceItems::toToman($price);
    }
}
