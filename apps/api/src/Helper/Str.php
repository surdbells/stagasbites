<?php

declare(strict_types=1);

namespace StagasBites\Helper;

final class Str
{
    public static function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $value : $ascii));

        return trim((string) $slug, '-');
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Formats minor units as a display amount, e.g. 2500 -> "$25.00". */
    public static function money(int $cents, string $currency = 'CAD'): string
    {
        $symbol = in_array($currency, ['CAD', 'USD'], true) ? '$' : $currency . ' ';

        return $symbol . number_format($cents / 100, 2);
    }

    public static function truncate(string $value, int $length): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length - 1)) . '…';
    }
}
