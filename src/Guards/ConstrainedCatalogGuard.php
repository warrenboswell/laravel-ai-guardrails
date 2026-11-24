<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

use Illuminate\Contracts\Support\Arrayable;
use WarrenBoswell\LaravelAiGuardrails\Results\CatalogGuardResult;
use WarrenBoswell\LaravelAiGuardrails\Support\NumberParser;

readonly class ConstrainedCatalogGuard
{
    public function __construct(
        private string $defaultCurrencySymbol = '$',
    ) {}

    /**
     * Re-hydrate matched catalog IDs from canonical PHP/Eloquent data and strip budget, stock, tag, or missing-ID violations.
     *
     * @param  iterable<array-key, array<string, mixed>|Arrayable<string, mixed>>  $catalogItems
     * @param  array<string, mixed>  $aiResult
     */
    public function enforce(
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
        $symbol = $currencySymbol ?? $this->defaultCurrencySymbol;
        $catalogList = [];
        $catalogById = [];

        foreach ($catalogItems as $item) {
            if ($item instanceof Arrayable) {
                $item = $item->toArray();
            }

            if (! is_array($item)) {
                continue;
            }

            $id = (string) ($item[$idKey] ?? '');

            if ($id !== '') {
                $catalogList[] = $item;
                $catalogById[$id] = $item;
            }
        }

        $rawFilters = is_array($aiResult['applied_filters'] ?? null) ? $aiResult['applied_filters'] : [];
        $maxBudget = NumberParser::toFloat($rawFilters[$budgetFilterKey] ?? null);
        $requireInStock = (bool) ($rawFilters['require_in_stock'] ?? false);
        $rawTags = is_array($rawFilters['required_tags'] ?? null) ? $rawFilters['required_tags'] : [];
        /** @var list<string> $requiredTags */
        $requiredTags = $this->normalizeTags($rawTags);

        $rawMatches = is_array($aiResult['matched_items'] ?? null) ? $aiResult['matched_items'] : [];
        $verified = [];
        $verifiedIds = [];
        $seenIds = [];
        $blockedCount = 0;

        foreach ($rawMatches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $itemId = (string) ($match[$matchIdKey] ?? $match[$idKey] ?? '');

            if ($itemId === '' || isset($seenIds[$itemId])) {
                continue;
            }

            $seenIds[$itemId] = true;

            if (! isset($catalogById[$itemId])) {
                $blockedCount++;

                continue;
            }

            $canonical = $catalogById[$itemId];
            $price = NumberParser::toFloat($canonical[$priceKey] ?? null);
            $inStock = (bool) ($canonical[$stockKey] ?? true);
            $canonicalTags = $this->normalizeTags(is_array($canonical[$tagsKey] ?? null) ? $canonical[$tagsKey] : []);

            if ($maxBudget !== null && $price !== null && $price > $maxBudget) {
                $blockedCount++;

                continue;
            }

            if ($requireInStock && ! $inStock) {
                $blockedCount++;

                continue;
            }

            if ($requiredTags !== [] && array_diff($requiredTags, $canonicalTags) !== []) {
                $blockedCount++;

                continue;
            }

            $verifiedIds[(string) $canonical[$idKey]] = true;
            $verified[] = [
                ...$canonical,
                'match_reason' => array_key_exists('match_reason', $canonical)
                   ? $canonical['match_reason']
                   : (string) ($match['match_reason'] ?? 'Matched canonical catalog attributes.'),
            ];
        }

        $activeCategories = array_values(array_unique(array_filter(
            array_map(
                static fn (array $item): string => is_string($item[$categoryKey] ?? null) ? $item[$categoryKey] : '',
                $verified,
            ),
            static fn (string $category): bool => $category !== '',
        )));

        $excludedItems = [];

        foreach ($catalogList as $candidate) {
            $candidateId = (string) ($candidate[$idKey] ?? '');

            if (isset($verifiedIds[$candidateId])) {
                continue;
            }

            $candidateCategory = is_string($candidate[$categoryKey] ?? null) ? $candidate[$categoryKey] : '';

            if ($activeCategories !== [] && $candidateCategory !== '' && ! in_array($candidateCategory, $activeCategories, true)) {
                continue;
            }

            $candidatePrice = NumberParser::toFloat($candidate[$priceKey] ?? null);
            $candidateInStock = (bool) ($candidate[$stockKey] ?? true);
            $candidateTags = $this->normalizeTags(is_array($candidate[$tagsKey] ?? null) ? $candidate[$tagsKey] : []);
            $reason = null;

            if ($maxBudget !== null && $candidatePrice !== null && $candidatePrice > $maxBudget) {
                $reason = sprintf('%s%s exceeds %s%s cap', $symbol, number_format($candidatePrice, 2), $symbol, number_format($maxBudget, 2));
            } elseif ($requireInStock && ! $candidateInStock) {
                $reason = 'Out of stock / unavailable';
            } elseif ($requiredTags !== [] && array_diff($requiredTags, $candidateTags) !== []) {
                $reason = 'Missing required specs';
            }

            if ($reason !== null) {
                $excludedItems[] = [
                    'name' => (string) ($candidate[$nameKey] ?? $candidateId),
                    'price' => $candidatePrice,
                    'reason' => $reason,
                ];
            }

            if (count($excludedItems) >= $maxExcludedSample) {
                break;
            }
        }

        $verifiedCount = count($verified);
        $guardHeadline = $blockedCount > 0
           ? "PHP Filter Guard Blocked $blockedCount Out-of-Bounds Match(es)"
           : 'PHP Catalog Guard Passed';
        $guardDetail = $blockedCount > 0
           ? sprintf(
               '%d canonical item(s) verified; %d candidate(s) stripped for missing ID, stock, tag, or budget bounds.',
               $verifiedCount,
               $blockedCount,
           )
           : sprintf(
               '%d canonical item(s) verified and %d constraint-failing item(s) excluded by PHP.',
               $verifiedCount,
               count($excludedItems),
           );

        return new CatalogGuardResult(
            interpretedIntent: (string) ($aiResult['interpreted_intent'] ?? ''),
            appliedFilters: [
                'max_budget' => $maxBudget,
                'require_in_stock' => $requireInStock,
                'required_tags' => $requiredTags,
            ],
            items: $verified,
            excludedItems: $excludedItems,
            verifiedCount: $verifiedCount,
            blockedCount: $blockedCount,
            guardHeadline: $guardHeadline,
            guardDetail: $guardDetail,
        );
    }

    /**
     * @param  array<array-key, mixed>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            $value = strtolower(trim((string) $tag));

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
