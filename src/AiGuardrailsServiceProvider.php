<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use WarrenBoswell\LaravelAiGuardrails\Guards\ArithmeticGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\CanonicalRecordGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\ConstrainedCatalogGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\PiiRedactionGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\PromptBoundaryGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\RateCardQuoteGuard;
use WarrenBoswell\LaravelAiGuardrails\Results\CanonicalGroundResult;
use WarrenBoswell\LaravelAiGuardrails\Results\CatalogGuardResult;

class AiGuardrailsServiceProvider extends ServiceProvider
{
    /**
     * Register the AI Guardrails singleton, guard bindings, and merge package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-guardrails.php', 'ai-guardrails');

        $this->app->singleton(AiGuardrails::class, static function (Application $app): AiGuardrails {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ai-guardrails', []);
            $currencySymbol = is_string($config['currency_symbol'] ?? null) && $config['currency_symbol'] !== ''
               ? $config['currency_symbol']
               : '$';
            $boundaryTag = is_string($config['boundary_tag'] ?? null) && $config['boundary_tag'] !== ''
               ? $config['boundary_tag']
               : 'sandbox_input';
            $tolerance = is_numeric($config['arithmetic_tolerance'] ?? null)
               ? (float) $config['arithmetic_tolerance']
               : 0.02;

            /** @var array<string, array<string, array<string, mixed>>> $rateCards */
            $rateCards = is_array($config['rate_cards'] ?? null) ? $config['rate_cards'] : [];
            $defaultRateCard = is_array($rateCards['default'] ?? null) ? $rateCards['default'] : [];

            return new AiGuardrails(
                promptBoundary: new PromptBoundaryGuard,
                piiRedaction: new PiiRedactionGuard,
                arithmetic: new ArithmeticGuard,
                canonicalRecord: new CanonicalRecordGuard,
                rateCardQuote: new RateCardQuoteGuard(
                    defaultCurrencySymbol: $currencySymbol,
                    defaultRateCard: $defaultRateCard,
                ),
                constrainedCatalog: new ConstrainedCatalogGuard(
                    defaultCurrencySymbol: $currencySymbol,
                ),
                defaultBoundaryTag: $boundaryTag,
                defaultArithmeticTolerance: $tolerance,
                defaultCurrencySymbol: $currencySymbol,
                rateCards: $rateCards,
            );
        });

        $this->app->bind(
            RateCardQuoteGuard::class,
            static fn (Application $app): RateCardQuoteGuard => $app->make(AiGuardrails::class)->rateCardQuote,
        );

        $this->app->bind(
            ConstrainedCatalogGuard::class,
            static fn (Application $app): ConstrainedCatalogGuard => $app->make(AiGuardrails::class)->constrainedCatalog,
        );
    }

    /**
     * Bootstrap publishable configuration and Laravel Collection macros.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-guardrails.php' => $this->app->configPath('ai-guardrails.php'),
            ], 'ai-guardrails-config');
        }

        $this->registerCollectionMacros();
    }

    private function registerCollectionMacros(): void
    {
        if (! Collection::hasMacro('groundAiMatches')) {
            Collection::macro('groundAiMatches', function (
                array $aiMatches,
                string $canonicalKey = 'id',
                string $matchKey = 'id',
                array $allowedAiFields = ['match_reason'],
                array $aiFieldDefaults = [],
                bool $renameCanonicalIdToMatchKey = false,
            ): CanonicalGroundResult {
                /** @var Collection<array-key, array<string, mixed>|Arrayable<string, mixed>> $this */
                return Container::getInstance()->make(AiGuardrails::class)->groundRecords(
                    canonicalRecords: $this,
                    aiMatches: $aiMatches,
                    canonicalKey: $canonicalKey,
                    matchKey: $matchKey,
                    allowedAiFields: $allowedAiFields,
                    aiFieldDefaults: $aiFieldDefaults,
                    renameCanonicalIdToMatchKey: $renameCanonicalIdToMatchKey,
                );
            });
        }

        if (! Collection::hasMacro('enforceAiCatalog')) {
            Collection::macro('enforceAiCatalog', function (
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
                /** @var Collection<array-key, array<string, mixed>|Arrayable<string, mixed>> $this */
                return Container::getInstance()->make(AiGuardrails::class)->enforceCatalog(
                    catalogItems: $this,
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
                    currencySymbol: $currencySymbol,
                );
            });
        }
    }
}
