<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used to decide when a user's daily alert must be sent.
    | The time a user picks in the bot is interpreted in this timezone.
    |
    */

    'timezone' => env('PRICE_ALERT_TIMEZONE', 'Asia/Tehran'),

    /*
    |--------------------------------------------------------------------------
    | Preset Times
    |--------------------------------------------------------------------------
    |
    | The quick time buttons shown to the user. The user can always type any
    | other time manually in the HH:MM format.
    |
    */

    'preset_times' => [
        '08:00',
        '10:00',
        '12:00',
        '14:00',
        '16:00',
        '18:00',
        '20:00',
        '22:00',
    ],

];
