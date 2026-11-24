<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use WarrenBoswell\LaravelAiGuardrails\Guards\ArithmeticGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\CanonicalRecordGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\ConstrainedCatalogGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\PiiRedactionGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\PromptBoundaryGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\RateCardQuoteGuard;
use WarrenBoswell\LaravelAiGuardrails\Results\ArithmeticAuditResult;
use WarrenBoswell\LaravelAiGuardrails\Results\CanonicalGroundResult;
use WarrenBoswell\LaravelAiGuardrails\Results\CatalogGuardResult;
use WarrenBoswell\LaravelAiGuardrails\Results\PiiRedactionResult;
use WarrenBoswell\LaravelAiGuardrails\Results\QuoteGuardResult;

readonly class AiGuardrails
{
    /**
     * @param  array<string, array<string, array<string, mixed>>>  $rateCards
     */
    public function __construct(
        public PromptBoundaryGuard $promptBoundary = new PromptBoundaryGuard,
        public PiiRedactionGuard $piiRedaction = new PiiRedactionGuard,
        public ArithmeticGuard $arithmetic = new ArithmeticGuard,
        public CanonicalRecordGuard $canonicalRecord = new CanonicalRecordGuard,
        public RateCardQuoteGuard $rateCardQuote = new RateCardQuoteGuard,
        public ConstrainedCatalogGuard $constrainedCatalog = new ConstrainedCatalogGuard,
        public string $defaultBoundaryTag = 'sandbox_input',
        public float $defaultArithmeticTolerance = 0.02,
        public string $defaultCurrencySymbol = '$',
        public array $rateCards = [],
    ) {}

    /**
     * Wrap untrusted user or document text inside a sanitized XML/HTML boundary tag.
     */
    public function wrapUntrustedInput(string $input, ?string $tag = null): string
    {
        return $this->promptBoundary->wrap($input, $tag ?? $this->defaultBoundaryTag);
    }

    /**
     * Strip spoofed opening/closing boundary tags from untrusted text.
     */
    public function stripBoundaryTags(string $input, ?string $tag = null): string
    {
        return $this->promptBoundary->strip($input, $tag ?? $this->defaultBoundaryTag);
    }

    /**
     * Redact PII and secret tokens into reversible numbered placeholders before calling an external LLM.
     *
     * @param  list<string>  $types
     */
    public function redactPii(string $input, array $types = PiiRedactionGuard::DEFAULT_TYPES): PiiRedactionResult
    {
        return $this->piiRedaction->redact($input, $types);
    }

    /**
     * Verify extracted financial totals and line items deterministically in PHP.
     *
     * @param  array<string, mixed>  $extracted
     */
    public function verifyArithmetic(array $extracted, ?float $tolerance = null): ArithmeticAuditResult
    {
        return $this->arithmetic->verify($extracted, $tolerance ?? $this->defaultArithmeticTolerance);
    }

    /**
     * Ground AI-selected record IDs against canonical PHP records or Eloquent models.
     *
     * @param  iterable<array-key, array<string, mixed>|Arrayable<string, mixed>>  $canonicalRecords
     * @param  array<int, mixed>  $aiMatches
     * @param  list<string>  $allowedAiFields
     * @param  array<string, mixed>  $aiFieldDefaults
     */
    public function groundRecords(
        iterable $canonicalRecords,
        array $aiMatches,
        string $canonicalKey = 'id',
        string $matchKey = 'id',
        array $allowedAiFields = ['match_reason'],
        array $aiFieldDefaults = [],
        bool $renameCanonicalIdToMatchKey = false,
    ): CanonicalGroundResult {
        return $this->canonicalRecord->ground(
            canonicalRecords: $canonicalRecords,
            aiMatches: $aiMatches,
            canonicalKey: $canonicalKey,
            matchKey: $matchKey,
            allowedAiFields: $allowedAiFields,
            aiFieldDefaults: $aiFieldDefaults,
            renameCanonicalIdToMatchKey: $renameCanonicalIdToMatchKey,
        );
    }

    /**
     * Calculate a deterministic ballpark quote from a fixed PHP rate card.
     *
     * @param  array<string, mixed>  $extracted
     * @param  array<string, array{label?: string, base: int|float, per_unit: int|float, unit_label?: string, default_units?: int}>  $rateCard
     * @param  array<string, float>  $urgencyMultipliers
     * @param  array<string, string>  $urgencySlas
     */
    public function calculateQuote(
        array $extracted,
        array $rateCard = [],
        array $urgencyMultipliers = [],
        array $urgencySlas = [],
        int $minUnitBound = 1,
        int $maxUnitBound = 500,
        ?string $currencySymbol = null,
        ?string $rateCardName = null,
    ): QuoteGuardResult {
        $resolvedRateCard = $rateCard;

        if ($resolvedRateCard === [] && $rateCardName !== null) {
            if (! isset($this->rateCards[$rateCardName]) || ! is_array($this->rateCards[$rateCardName])) {
                throw new InvalidArgumentException("Configured rate card [$rateCardName] was not found.");
            }

            $resolvedRateCard = $this->rateCards[$rateCardName];
        }

        return $this->rateCardQuote->calculate(
            extracted: $extracted,
            rateCard: $resolvedRateCard,
            urgencyMultipliers: $urgencyMultipliers,
            urgencySlas: $urgencySlas,
            minUnitBound: $minUnitBound,
            maxUnitBound: $maxUnitBound,
            currencySymbol: $currencySymbol ?? $this->defaultCurrencySymbol,
        );
    }

    /**
     * Enforce canonical catalog IDs, budget ceilings, stock availability, and required tags.
     *
     * @param  iterable<array-key, array<string, mixed>|Arrayable<string, mixed>>  $catalogItems
     * @param  array<string, mixed>  $aiResult
     */
    public function enforceCatalog(
        iterable $catalogItems,
        array $aiResult,
        int $maxExcludedSample = 4,
        string $idKey = 'id',
        string $matchIdKey = 'item_id',
        string $priceKey = 'price',
        string $stockKey = 'in_stock',
        string $tagsKey = 'tags',
        string $categoryKey = 'category',
        string $nameKey = 'name',
        string $budgetFilterKey = 'max_budget',
        ?string $currencySymbol = null,
    ): CatalogGuardResult {
        return $this->constrainedCatalog->enforce(
            catalogItems: $catalogItems,
            aiResult: $aiResult,
            maxExcludedSample: $maxExcludedSample,
            idKey: $idKey,
            matchIdKey: $matchIdKey,
            priceKey: $priceKey,
            stockKey: $stockKey,
            tagsKey: $tagsKey,
            categoryKey: $categoryKey,
            nameKey: $nameKey,
            budgetFilterKey: $budgetFilterKey,
            currencySymbol: $currencySymbol ?? $this->defaultCurrencySymbol,
        );
    }
}
