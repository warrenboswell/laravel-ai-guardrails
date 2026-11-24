<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Facades;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Facade;
use WarrenBoswell\LaravelAiGuardrails\AiGuardrails as AiGuardrailsManager;
use WarrenBoswell\LaravelAiGuardrails\Guards\PiiRedactionGuard;
use WarrenBoswell\LaravelAiGuardrails\Results\ArithmeticAuditResult;
use WarrenBoswell\LaravelAiGuardrails\Results\CanonicalGroundResult;
use WarrenBoswell\LaravelAiGuardrails\Results\CatalogGuardResult;
use WarrenBoswell\LaravelAiGuardrails\Results\PiiRedactionResult;
use WarrenBoswell\LaravelAiGuardrails\Results\QuoteGuardResult;

/**
 * @method static string wrapUntrustedInput(string $input, ?string $tag = null)
 * @method static string stripBoundaryTags(string $input, ?string $tag = null)
 * @method static PiiRedactionResult redactPii(string $input, list<string> $types = PiiRedactionGuard::DEFAULT_TYPES)
 * @method static ArithmeticAuditResult verifyArithmetic(array<string, mixed> $extracted, ?float $tolerance = null)
 * @method static CanonicalGroundResult groundRecords(iterable<int|string, array|Arrayable> $canonicalRecords, array<int, mixed> $aiMatches, string $canonicalKey = 'id', string $matchKey = 'id', list<string> $allowedAiFields = ['match_reason'], array<string, mixed> $aiFieldDefaults = [], bool $renameCanonicalIdToMatchKey = false)
 * @method static QuoteGuardResult calculateQuote(array<string, mixed> $extracted, array<string, mixed> $rateCard = [], array<string, float> $urgencyMultipliers = [], array<string, string> $urgencySlas = [], int $minUnitBound = 1, int $maxUnitBound = 500, ?string $currencySymbol = null, ?string $rateCardName = null)
 * @method static CatalogGuardResult enforceCatalog(iterable<int|string, array|Arrayable> $catalogItems, array<string, mixed> $aiResult, int $maxExcludedSample = 4, string $idKey = 'id', string $matchIdKey = 'item_id', string $priceKey = 'price', string $stockKey = 'in_stock', string $tagsKey = 'tags', string $categoryKey = 'category', string $nameKey = 'name', string $budgetFilterKey = 'max_budget', ?string $currencySymbol = null)
 *
 * @see AiGuardrailsManager
 */
class AiGuardrails extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiGuardrailsManager::class;
    }
}
