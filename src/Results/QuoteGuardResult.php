<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Results;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class QuoteGuardResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $categoryLabel,
        public string $unitLabel,
        public int $unitCount,
        public string $urgency,
        public int $minAmount,
        public int $maxAmount,
        public int $midpointAmount,
        public string $currencySymbol,
        public string $targetSla,
        public string $formulaBreakdown,
        public string $headline,
        public string $detail,
    ) {}

    /**
     * Convert the deterministic quote result to the canonical array shape.
     *
     * @return array{
     *     category_label: string,
     *     unit_label: string,
     *     unit_count: int,
     *     urgency: string,
     *     min_amount: int,
     *     max_amount: int,
     *     midpoint_amount: int,
     *     currency_symbol: string,
     *     target_sla: string,
     *     formula_breakdown: string,
     *     headline: string,
     *     detail: string
     * }
     */
    public function toArray(): array
    {
        return [
            'category_label' => $this->categoryLabel,
            'unit_label' => $this->unitLabel,
            'unit_count' => $this->unitCount,
            'urgency' => $this->urgency,
            'min_amount' => $this->minAmount,
            'max_amount' => $this->maxAmount,
            'midpoint_amount' => $this->midpointAmount,
            'currency_symbol' => $this->currencySymbol,
            'target_sla' => $this->targetSla,
            'formula_breakdown' => $this->formulaBreakdown,
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
