<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

use WarrenBoswell\LaravelAiGuardrails\Results\PiiRedactionResult;

class PiiRedactionGuard
{
    /**
     * Default redaction detector types in evaluation order.
     *
     * @var list<string>
     */
    public const DEFAULT_TYPES = [
        'api_key',
        'credit_card',
        'email',
        'ipv4',
        'phone',
    ];

    /**
     * Redact PII and secret tokens into reversible numbered placeholders before calling an external LLM.
     *
     * @param  list<string>  $types
     */
    public function redact(string $input, array $types = self::DEFAULT_TYPES): PiiRedactionResult
    {
        $activeTypes = $types !== [] ? array_values(array_unique($types)) : self::DEFAULT_TYPES;
        $text = $input;
        $tokenMap = [];
        $valueToPlaceholder = [];
        $typeCounters = [];
        $detectedTypes = [];
        $redactedCount = 0;

        $assignPlaceholder = static function (string $type, string $placeholderPrefix, string $rawValue) use (
            &$tokenMap,
            &$valueToPlaceholder,
            &$typeCounters,
            &$detectedTypes,
            &$redactedCount,
        ): string {
            $redactedCount++;
            $detectedTypes[$type] = true;

            if (isset($valueToPlaceholder[$rawValue])) {
                return $valueToPlaceholder[$rawValue];
            }

            $typeCounters[$type] = ($typeCounters[$type] ?? 0) + 1;
            $placeholder = sprintf('[%s_%d]', $placeholderPrefix, $typeCounters[$type]);
            $valueToPlaceholder[$rawValue] = $placeholder;
            $tokenMap[$placeholder] = $rawValue;

            return $placeholder;
        };

        if (in_array('api_key', $activeTypes, true)) {
            $apiKeyPattern = '/\b(?:(?:sk|rk|pk)-(?:proj-|ant-|live-|test-)?[a-zA-Z0-9_-]{16,}|(?:ghp|gho|ghu|ghs|ghr)_[a-zA-Z0-9]{20,}|AIza[0-9A-Za-z_-]{35}|xox[baprs]-[0-9A-Za-z-]{16,}|AKIA[0-9A-Z]{16})\b/';
            $text = preg_replace_callback(
                $apiKeyPattern,
                static fn (array $matches): string => $assignPlaceholder('api_key', 'API_KEY', $matches[0]),
                $text,
            ) ?? $text;
        }

        if (in_array('credit_card', $activeTypes, true)) {
            $cardPattern = '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/';
            $text = preg_replace_callback(
                $cardPattern,
                function (array $matches) use ($assignPlaceholder): string {
                    $candidate = $matches[0];
                    $digits = preg_replace('/\D/', '', $candidate) ?? '';

                    if (! $this->passesLuhn($digits)) {
                        return $candidate;
                    }

                    return $assignPlaceholder('credit_card', 'CREDIT_CARD', $candidate);
                },
                $text,
            ) ?? $text;
        }

        if (in_array('email', $activeTypes, true)) {
            $emailPattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/';
            $text = preg_replace_callback(
                $emailPattern,
                static fn (array $matches): string => $assignPlaceholder('email', 'EMAIL', $matches[0]),
                $text,
            ) ?? $text;
        }

        if (in_array('ipv4', $activeTypes, true)) {
            $ipv4Pattern = '/(?<!\d)(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?!\d)/';
            $text = preg_replace_callback(
                $ipv4Pattern,
                static fn (array $matches): string => $assignPlaceholder('ipv4', 'IPV4', $matches[0]),
                $text,
            ) ?? $text;
        }

        if (in_array('phone', $activeTypes, true)) {
            $phonePattern = '/(?<![\d.])(?:\+\d{1,3}[\s.-]?(?:\(\d{1,5}\)[\s.-]?)?(?:\d[\s.-]?){7,13}\d\b|(?:\(\d{2,5}\)[\s.-]?|\b\d{3,5}[\s.-])\d{3,4}[\s.-]\d{3,4}\b)/';
            $text = preg_replace_callback(
                $phonePattern,
                static function (array $matches) use ($assignPlaceholder): string {
                    $candidate = $matches[0];
                    $digits = preg_replace('/\D/', '', $candidate) ?? '';
                    $digitCount = strlen($digits);

                    if ($digitCount < 10 || $digitCount > 15) {
                        return $candidate;
                    }

                    return $assignPlaceholder('phone', 'PHONE', $candidate);
                },
                $text,
            ) ?? $text;
        }

        $detectedList = array_keys($detectedTypes);
        $headline = $redactedCount > 0
           ? "PHP PII Guard Redacted $redactedCount Sensitive Token(s)"
           : 'PHP PII Guard Passed Cleanly';
        $detail = $redactedCount > 0
           ? sprintf(
               'Redacted %d sensitive occurrence(s) across [%s] into %d reversible placeholder(s).',
               $redactedCount,
               implode(', ', $detectedList),
               count($tokenMap),
           )
           : 'No API keys, Luhn-valid payment cards, emails, IPv4 addresses, or phone numbers detected.';

        return new PiiRedactionResult(
            redactedText: $text,
            redactedCount: $redactedCount,
            tokenMap: $tokenMap,
            detectedTypes: $detectedList,
            headline: $headline,
            detail: $detail,
        );
    }

    /**
     * Verify whether a 13-to-19 digit string satisfies the Luhn payment card checksum.
     */
    public function passesLuhn(string $digits): bool
    {
        $length = strlen($digits);

        if ($length < 13 || $length > 19 || ! ctype_digit($digits)) {
            return false;
        }

        if (trim($digits, '0') === '') {
            return false;
        }

        $sum = 0;
        $shouldDouble = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($shouldDouble) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $shouldDouble = ! $shouldDouble;
        }

        return $sum % 10 === 0;
    }
}
