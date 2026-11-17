<?php

namespace App\Support;

/**
 * Single source of truth for the items supported by the bot and how they
 * are displayed, normalized and converted to Toman.
 */
final class PriceItems
{
    /**
     * Key used by the tgju API => label and emoji shown to the user.
     *
     * @var array<string, array{label: string, emoji: string}>
     */
    public const ITEMS = [
        'price_dollar_rl' => ['label' => 'دلار', 'emoji' => '💵'],
        'price_eur' => ['label' => 'یورو', 'emoji' => '💶'],
        'sekee' => ['label' => 'سکه بهار آزادی', 'emoji' => '🪙'],
        'rob' => ['label' => 'ربع سکه', 'emoji' => '🪙'],
        'nim' => ['label' => 'نیم سکه', 'emoji' => '🪙'],
        'gold_mini_size' => ['label' => 'طلای دست دوم', 'emoji' => '🥇'],
        'crypto-bitcoin-irr' => ['label' => 'بیت کوین', 'emoji' => '₿'],
        'crypto-tether-irr' => ['label' => 'تتر', 'emoji' => '₮'],
        'crypto-ripple-irr' => ['label' => 'ریپل', 'emoji' => '✳️'],
    ];

    /**
     * @return array<string, array{label: string, emoji: string}>
     */
    public static function all(): array
    {
        return self::ITEMS;
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::ITEMS);
    }

    public static function label(string $key): string
    {
        return self::ITEMS[$key]['label'] ?? $key;
    }

    public static function emoji(string $key): string
    {
        return self::ITEMS[$key]['emoji'] ?? '';
    }

    /**
     * The label used on reply/inline keyboards, e.g. "💵 دلار".
     */
    public static function keyboardLabel(string $key): string
    {
        return trim(self::emoji($key).' '.self::label($key));
    }

    /**
     * Resolve an item key from an arbitrary (keyboard) text.
     *
     * Emojis, punctuation and extra spaces are ignored, so both
     * "💵 دلار" and "دلار" resolve to "price_dollar_rl".
     */
    public static function fromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '');

        foreach (self::ITEMS as $key => $item) {
            if ($item['label'] === $normalized) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Convert a raw tgju price (Rial) to a formatted Toman string.
     */
    public static function toToman(mixed $price): string
    {
        $price = str_replace(',', '', (string) $price);

        return number_format((int) $price / 10);
    }

    /**
     * Format one line of a daily alert message.
     *
     * @param  array<string, mixed>  $data
     */
    public static function formatAlertLine(string $key, array $data): string
    {
        $price = self::toToman($data['p'] ?? 0);

        $line = self::keyboardLabel($key).': '.$price.' تومان';

        $changePercent = (float) ($data['dp'] ?? 0);

        if ($changePercent !== 0.0) {
            $arrow = $changePercent > 0 ? '🔺' : '🔻';
            $line .= ' '.$arrow.' '.number_format(abs($changePercent), 2).'%';
        }

        return $line;
    }

    /**
     * Convert Persian/Arabic digits of a string to English digits.
     */
    public static function normalizeDigits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    /**
     * Join the labels of the given item keys, e.g. "💵 دلار، 💶 یورو".
     *
     * @param  array<int, string>  $keys
     */
    public static function labels(array $keys): string
    {
        return implode('، ', array_map(
            static fn (string $key): string => self::keyboardLabel($key),
            $keys
        ));
    }
}
