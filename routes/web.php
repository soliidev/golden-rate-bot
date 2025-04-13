<?php

use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::get('/', static function () {
    return view('welcome');
});

Route::post('telegram/token/bot', static function (Nutgram $bot) {
    $bot->run();
});
