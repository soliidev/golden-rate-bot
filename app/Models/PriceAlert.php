<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceAlert extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'chat_id',
        'telegram_user_id',
        'username',
        'first_name',
        'notify_time',
        'timezone',
        'items',
        'is_active',
        'last_sent_on',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_active' => 'boolean',
            'last_sent_on' => 'date',
        ];
    }
}
