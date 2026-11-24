<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class PiiRedactionResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, string>  $tokenMap
     * @param  list<string>  $detectedTypes
     */
    public function __construct(
        public string $redactedText,
        public int $redactedCount,
        public array $tokenMap,
        public array $detectedTypes,
        public string $headline,
        public string $detail,
    ) {}

    /**
     * Determine whether any PII or secret tokens were redacted.
     */
    public function hasRedactions(): bool
    {
        return $this->redactedCount > 0;
    }

    /**
     * Deterministically restore redacted placeholders (such as [EMAIL_1]) in an LLM response.
     */
    public function rehydrate(string $text): string
    {
        if ($this->tokenMap === []) {
            return $text;
        }

        return strtr($text, $this->tokenMap);
    }

    /**
     * Convert the redaction audit result to a log-safe array (omits raw secret values from tokenMap).
     *
     * @return array{
     *     redacted_text: string,
     *     redacted_count: int,
     *     detected_types: list<string>,
     *     placeholders: list<string>,
     *     headline: string,
     *     detail: string
     * }
     */
    public function toArray(): array
    {
        return [
            'redacted_text' => $this->redactedText,
            'redacted_count' => $this->redactedCount,
            'detected_types' => $this->detectedTypes,
            'placeholders' => array_keys($this->tokenMap),
            'headline' => $this->headline,
            'detail' => $this->detail,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
