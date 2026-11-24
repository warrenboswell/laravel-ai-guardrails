<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class CatalogGuardResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array{max_budget: float|null, require_in_stock: bool, required_tags: list<string>}  $appliedFilters
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{name: string, price: float|null, reason: string}>  $excludedItems
     */
    public function __construct(
        public string $interpretedIntent,
        public array $appliedFilters,
        public array $items,
        public array $excludedItems,
        public int $verifiedCount,
        public int $blockedCount,
        public string $guardHeadline,
        public string $guardDetail,
    ) {}

    /**
     * Build a deterministic self-correction prompt when out-of-bounds catalog items were blocked.
     */
    public function toRetryPrompt(?string $additionalInstructions = null): ?string
    {
        if ($this->blockedCount === 0) {
            return null;
        }

        $exclusions = array_map(
            static fn (array $item): string => sprintf('%s (%s)', $item['name'], $item['reason']),
            $this->excludedItems,
        );

        $prompt = sprintf(
            'Catalog constraint verification failed (%s): %s',
            $this->guardHeadline,
            $this->guardDetail,
        );

        if ($exclusions !== []) {
            $prompt .= sprintf(' Excluded candidates: [%s].', implode('; ', $exclusions));
        }

        $prompt .= ' Select only canonical catalog items that satisfy all budget, stock, and tag constraints.';

        if ($additionalInstructions !== null && trim($additionalInstructions) !== '') {
            $prompt .= ' '.trim($additionalInstructions);
        }

        return $prompt;
    }

    /**
     * Convert the catalog guard result to the canonical array shape.
     *
     * @return array{
     *     interpreted_intent: string,
     *     applied_filters: array{max_budget: float|null, require_in_stock: bool, required_tags: list<string>},
     *     items: list<array<string, mixed>>,
     *     excluded_items: list<array{name: string, price: float|null, reason: string}>,
     *     verified_count: int,
     *     blocked_count: int,
     *     guard_headline: string,
     *     guard_detail: string
     * }
     */
    public function toArray(): array
    {
        return [
            'interpreted_intent' => $this->interpretedIntent,
            'applied_filters' => $this->appliedFilters,
            'items' => $this->items,
            'excluded_items' => $this->excludedItems,
            'verified_count' => $this->verifiedCount,
            'blocked_count' => $this->blockedCount,
            'guard_headline' => $this->guardHeadline,
            'guard_detail' => $this->guardDetail,
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
