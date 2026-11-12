<?php

namespace App\Support;

/**
 * Phone number comparison for Indonesian numbers written in many styles
 * ("0877-3910-9686", "+62 877 3910 9686", "(021) 731 2895", "021 - 7312895/96").
 */
class PhoneNumbers
{
    /** Shortest number (digits, without leading zero) worth comparing. */
    const MIN_DIGITS = 7;

    /**
     * Digits only, country code 62 turned into 0, then without leading zeros.
     *
     * @param  string  $raw
     * @return string
     */
    public static function canonical($raw)
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        }

        return ltrim($digits, '0');
    }

    /**
     * Official numbers from free-text store fields (telp, whatsapp, ...).
     * Handles separators (; , newline) and shorthand such as "7312895/96".
     *
     * @param  array  $fields
     * @return array  Canonical numbers
     */
    public static function officialNumbers(array $fields)
    {
        $numbers = [];

        foreach ($fields as $text) {
            foreach (preg_split('/[;,\n]+/', (string) $text) as $chunk) {
                $parts = explode('/', $chunk);
                $first = preg_replace('/\D+/', '', $parts[0]);
                $numbers[] = self::canonical($first);

                for ($i = 1; $i < count($parts); $i++) {
                    $digits = preg_replace('/\D+/', '', $parts[$i]);
                    if ($digits === '') {
                        continue;
                    }
                    // "…7312895/96": a short tail replaces the last digits of the previous number.
                    if (strlen($digits) <= 4 && strlen($first) > strlen($digits)) {
                        $digits = substr($first, 0, strlen($first) - strlen($digits)) . $digits;
                    }
                    $numbers[] = self::canonical($digits);
                }
            }
        }

        return array_values(array_unique(array_filter($numbers, function ($n) {
            return strlen($n) >= self::MIN_DIGITS - 1;
        })));
    }

    /**
     * Does a number read from a photo belong to one of the official numbers?
     * A number without area code matches when it is the tail of an official one.
     *
     * @param  string  $found
     * @param  array  $official  Canonical numbers from officialNumbers()
     * @return bool
     */
    public static function matches($found, array $official)
    {
        $found = self::canonical($found);
        if ($found === '') {
            return false;
        }

        foreach ($official as $number) {
            if ($found === $number) {
                return true;
            }
            $short = strlen($found) < strlen($number) ? $found : $number;
            $long = $short === $found ? $number : $found;
            if (strlen($short) >= self::MIN_DIGITS && substr($long, -strlen($short)) === $short) {
                return true;
            }
        }

        return false;
    }

    /**
     * Closest official number to a number read from a photo.
     *
     * @param  string  $found
     * @param  array  $official  Canonical numbers
     * @return array|null  [canonical official number, digit distance]
     */
    public static function nearest($found, array $official)
    {
        $found = self::canonical($found);
        $min = (int) config('photo_ai.near_match_min_digits', 9);
        if (strlen($found) < $min) {
            return null;
        }

        $best = null;
        foreach ($official as $number) {
            if (strlen($number) < $min) {
                continue;
            }
            $distance = levenshtein($found, $number);
            if ($best === null || $distance < $best[1]) {
                $best = [$number, $distance];
            }
        }

        return $best;
    }

    /**
     * Not an official number, but close to one (1..max digits apart).
     *
     * @param  string  $found
     * @param  array  $official
     * @return array|null  [canonical official number, digit distance] when near
     */
    public static function near($found, array $official)
    {
        $nearest = self::nearest($found, $official);
        $max = (int) config('photo_ai.near_match_max_distance', 2);

        return $nearest && $nearest[1] >= 1 && $nearest[1] <= $max ? $nearest : null;
    }

    /**
     * A canonical number in the way people write it (leading 0).
     *
     * @param  string  $canonical
     * @return string
     */
    public static function display($canonical)
    {
        return '0' . $canonical;
    }

    /**
     * Is this string long enough to be a phone number worth checking?
     *
     * @param  string  $raw
     * @return bool
     */
    public static function isComparable($raw)
    {
        return strlen(self::canonical($raw)) >= self::MIN_DIGITS;
    }
}
