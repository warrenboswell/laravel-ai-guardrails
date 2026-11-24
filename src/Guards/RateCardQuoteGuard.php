<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

use InvalidArgumentException;
use WarrenBoswell\LaravelAiGuardrails\Results\QuoteGuardResult;

readonly class RateCardQuoteGuard
{
    /**
     * @param  array<string, array<string, mixed>>  $defaultRateCard
     */
    public function __construct(
        private string $defaultCurrencySymbol = '$',
        private array $defaultRateCard = [],
    ) {}

    /**
     * Calculate a deterministic ballpark quote and SLA tier from a fixed PHP rate card.
     *
     * @param  array<string, mixed>  $extracted
     * @param  array<string, array{label?: string, base: int|float, per_unit: int|float, unit_label?: string, default_units?: int}>  $rateCard
     * @param  array<string, float>  $urgencyMultipliers
     * @param  array<string, string>  $urgencySlas
     */
    public function calculate(
        array $extracted,
        array $rateCard = [],
        array $urgencyMultipliers = [],
        array $urgencySlas = [],
        int $minUnitBound = 1,
        int $maxUnitBound = 500,
        ?string $currencySymbol = null,
    ): QuoteGuardResult {
        $effectiveRateCard = $rateCard !== [] ? $rateCard : $this->defaultRateCard;

        if ($effectiveRateCard === []) {
            throw new InvalidArgumentException(
                'A non-empty rate card must be provided to RateCardQuoteGuard::calculate() or configured in ai-guardrails.rate_cards.default.'
            );
        }

        $category = (string) ($extracted['service_category'] ?? 'other');

        if (! isset($effectiveRateCard[$category])) {
            $category = isset($effectiveRateCard['other'])
               ? 'other'
               : (string) array_key_first($effectiveRateCard);
        }

        $tier = $effectiveRateCard[$category];

        if (! isset($tier['base'], $tier['per_unit']) || ! is_numeric($tier['base']) || ! is_numeric($tier['per_unit'])) {
            throw new InvalidArgumentException(
                "Rate card tier [$category] must define numeric 'base' and 'per_unit' values."
            );
        }

        $label = is_string($tier['label'] ?? null) && $tier['label'] !== '' ? $tier['label'] : $category;
        $unitLabel = is_string($tier['unit_label'] ?? null) && $tier['unit_label'] !== '' ? $tier['unit_label'] : 'unit(s)';
        $defaultUnits = is_numeric($tier['default_units'] ?? null) ? (int) $tier['default_units'] : 1;
        $base = (float) $tier['base'];
        $perUnit = (float) $tier['per_unit'];

        $rawUnits = is_numeric($extracted['unit_count'] ?? null)
           ? (int) $extracted['unit_count']
           : $defaultUnits;
        $units = max($minUnitBound, min($maxUnitBound, $rawUnits));

        $urgency = strtolower((string) ($extracted['urgency'] ?? 'standard'));
        $multipliers = $urgencyMultipliers !== [] ? $urgencyMultipliers : [
            'emergency' => 1.45,
            'priority' => 1.20,
            'standard' => 1.00,
        ];
        $slas = $urgencySlas !== [] ? $urgencySlas : [
            'emergency' => 'Same-Day Callback (< 2 hrs)',
            'priority' => 'Next-Business-Day Scoping',
            'standard' => '48-Hour Proposal Turnaround',
        ];

        $multiplier = $multipliers[$urgency] ?? $multipliers['standard'] ?? 1.00;
        $sla = $slas[$urgency] ?? $slas['standard'] ?? '48-Hour Proposal Turnaround';
        $symbol = $currencySymbol ?? $this->defaultCurrencySymbol;

        $midpoint = (int) round(($base + ($perUnit * $units)) * $multiplier);
        $minAmount = (int) (floor(($midpoint * 0.90) / 10) * 10);
        $maxAmount = (int) (ceil(($midpoint * 1.15) / 10) * 10);
        $formulaBreakdown = sprintf(
            '%s%s base + (%d %s × %s%s) × %.2fx %s SLA',
            $symbol,
            number_format($base),
            $units,
            $unitLabel,
            $symbol,
            number_format($perUnit),
            $multiplier,
            $urgency,
        );

        return new QuoteGuardResult(
            categoryLabel: $label,
            unitLabel: $unitLabel,
            unitCount: $units,
            urgency: $urgency,
            minAmount: $minAmount,
            maxAmount: $maxAmount,
            midpointAmount: $midpoint,
            currencySymbol: $symbol,
            targetSla: $sla,
            formulaBreakdown: $formulaBreakdown,
            headline: 'PHP Rate-Card Estimate Locked',
            detail: sprintf(
                'Calculated from fixed %s rate card (%s) — LLM never sets prices.',
                $label,
                $formulaBreakdown,
            ),
        );
    }
}
