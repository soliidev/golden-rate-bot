<?php

namespace App\Services\CurrentItemPrice;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class CurrentItemPriceService
{
    /**
     * @throws RequestException
     */
    public function getCurrentItemPrice()
    {
        $response = Http::get('https://call3.tgju.org/ajax.json?rev=zVBTthZQ7WQaBOgP5b8IMibCo9zJS3rrRswWR0leAF8XpThGTpdNnpRIQzdC');

        $response->throw();

        return $response->json()['current'] ?? [];
    }
}
