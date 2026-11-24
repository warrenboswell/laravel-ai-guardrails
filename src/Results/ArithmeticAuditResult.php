<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class ArithmeticAuditResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $status,
        public string $headline,
        public string $detail,
        public ?float $computedTotal = null,
        public ?float $extractedTotal = null,
        public ?float $lineItemsSum = null,
        public ?float $discrepancy = null,
    ) {}

    /**
     * Determine whether the arithmetic check passed deterministically.
     */
    public function verified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Determine whether an arithmetic or line-item mismatch was caught.
     */
    public function hasMismatch(): bool
    {
        return $this->status === 'mismatch';
    }

    /**
     * Build a deterministic self-correction prompt when arithmetic verification fails.
     */
    public function toRetryPrompt(?string $additionalInstructions = null): ?string
    {
        if (! $this->hasMismatch()) {
            return null;
        }

        $prompt = sprintf(
            'Arithmetic verification failed (%s): %s Re-inspect the source document, recalculate the exact figures without rounding drift, and return the corrected JSON payload.',
            $this->headline,
            $this->detail,
        );

        if ($additionalInstructions !== null && trim($additionalInstructions) !== '') {
            $prompt .= ' '.trim($additionalInstructions);
        }

        return $prompt;
    }

    /**
     * Convert the audit result to the canonical array shape.
     *
     * @return array{
     *     status: string,
     *     headline: string,
     *     detail: string,
     *     computed_total: float|null,
     *     extracted_total: float|null,
     *     line_items_sum: float|null,
     *     discrepancy: float|null
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'detail' => $this->detail,
            'computed_total' => $this->computedTotal,
            'extracted_total' => $this->extractedTotal,
            'line_items_sum' => $this->lineItemsSum,
            'discrepancy' => $this->discrepancy,
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
