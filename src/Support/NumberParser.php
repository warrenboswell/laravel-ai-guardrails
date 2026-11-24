<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Support;

class NumberParser
{
    /**
     * Normalize an integer, float, or currency-formatted string into a rounded float.
     */
    public static function toFloat(mixed $value, int $precision = 2): ?float
    {
        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value)) {
                return null;
            }

            return round((float) $value, $precision);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            $isAccountingNegative = str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')');

            if ($isAccountingNegative) {
                $trimmed = substr($trimmed, 1, -1);
            }

            $stripped = preg_replace('/\b(?:GBP|USD|EUR|CAD|AUD|NZD|CHF|JPY|INR)\b/i', '', $trimmed) ?? $trimmed;
            $normalized = str_replace([',', '£', '$', '€', '¥', '₹', ' '], '', trim($stripped));

            if ($normalized !== '' && is_numeric($normalized)) {
                $number = (float) $normalized;

                if ($isAccountingNegative && $number > 0) {
                    $number *= -1;
                }

                return round($number, $precision);
            }
        }

        return null;
    }
}
