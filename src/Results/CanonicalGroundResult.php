<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Results;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class CanonicalGroundResult implements Arrayable, Countable, JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $strippedIds
     * @param  list<string>  $duplicateIds
     */
    public function __construct(
        public array $items,
        public int $verifiedCount,
        public int $strippedCount,
        public array $strippedIds,
        public array $duplicateIds,
        public string $headline,
        public string $detail,
    ) {}

    public function count(): int
    {
        return $this->verifiedCount;
    }

    public function hasStrippedRecords(): bool
    {
        return $this->strippedCount > 0;
    }

    /**
     * Build a deterministic self-correction prompt when invalid or duplicate record IDs were stripped.
     */
    public function toRetryPrompt(?string $additionalInstructions = null): ?string
    {
        if ($this->strippedCount === 0 && $this->duplicateIds === []) {
            return null;
        }

        $parts = [
            sprintf('Canonical record verification failed (%s): %s', $this->headline, $this->detail),
        ];

        if ($this->strippedIds !== []) {
            $parts[] = sprintf('Invalid/hallucinated IDs rejected: [%s].', implode(', ', $this->strippedIds));
        }

        if ($this->duplicateIds !== []) {
            $parts[] = sprintf('Duplicate IDs rejected: [%s].', implode(', ', $this->duplicateIds));
        }

        $parts[] = 'Return only unique record IDs that exist verbatim in the provided canonical dataset.';

        if ($additionalInstructions !== null && trim($additionalInstructions) !== '') {
            $parts[] = trim($additionalInstructions);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     verified_count: int,
     *     stripped_count: int,
     *     stripped_ids: list<string>,
     *     duplicate_ids: list<string>,
     *     headline: string,
     *     detail: string
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'verified_count' => $this->verifiedCount,
            'stripped_count' => $this->strippedCount,
            'stripped_ids' => $this->strippedIds,
            'duplicate_ids' => $this->duplicateIds,
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
