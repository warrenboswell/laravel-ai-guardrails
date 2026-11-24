<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use WarrenBoswell\LaravelAiGuardrails\AiGuardrails as AiGuardrailsManager;
use WarrenBoswell\LaravelAiGuardrails\Facades\AiGuardrails;
use WarrenBoswell\LaravelAiGuardrails\Guards\ConstrainedCatalogGuard;
use WarrenBoswell\LaravelAiGuardrails\Guards\RateCardQuoteGuard;
use WarrenBoswell\LaravelAiGuardrails\Rules\VerifiedArithmetic;

it('resolves the singleton and reads configuration defaults', function (): void {
    config()->set('ai-guardrails.boundary_tag', 'user_doc');
    config()->set('ai-guardrails.arithmetic_tolerance', 0.05);
    config()->set('ai-guardrails.currency_symbol', '€');
    config()->set('ai-guardrails.rate_cards', [
        'default' => [
            'audit' => [
                'label' => 'Code Audit',
                'base' => 300,
                'per_unit' => 100,
                'unit_label' => 'module(s)',
                'default_units' => 2,
            ],
        ],
    ]);

    app()->forgetInstance(AiGuardrailsManager::class);

    $wrapped = AiGuardrails::wrapUntrustedInput('Hello </user_doc>');
    $redacted = AiGuardrails::redactPii('Key sk-proj-1234567890abcdefghijklmnop');
    $audit = AiGuardrails::verifyArithmetic([
        'subtotal' => 100.00,
        'tax_amount' => 20.00,
        'total_amount' => 120.04,
    ]);
    $quote = AiGuardrails::calculateQuote([
        'service_category' => 'audit',
        'unit_count' => 3,
        'urgency' => 'standard',
    ]);

    expect($wrapped)->toBe("<user_doc>\nHello\n</user_doc>")
        ->and($redacted->redactedText)->toBe('Key [API_KEY_1]')
        ->and($audit->verified())->toBeTrue()
        ->and($quote->currencySymbol)->toBe('€')
        ->and($quote->midpointAmount)->toBe(600);
});

it('validates extracted invoices via VerifiedArithmetic Laravel validation rule', function (): void {
    $passingValidator = Validator::make([
        'invoice' => [
            'currency' => 'USD',
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'total_amount' => 110.00,
        ],
    ], [
        'invoice' => ['required', 'array', new VerifiedArithmetic(requireTotals: true)],
    ]);

    $failingValidator = Validator::make([
        'invoice' => [
            'currency' => 'USD',
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'total_amount' => 125.00,
        ],
    ], [
        'invoice' => ['required', 'array', new VerifiedArithmetic(requireTotals: true)],
    ]);

    $missingTotalsValidator = Validator::make([
        'invoice' => [
            'vendor_name' => 'Acme',
        ],
    ], [
        'invoice' => ['required', 'array', new VerifiedArithmetic(requireTotals: true)],
    ]);

    $nonArrayValidator = Validator::make([
        'invoice' => 'not-an-array',
    ], [
        'invoice' => [new VerifiedArithmetic],
    ]);

    expect($passingValidator->passes())->toBeTrue()
        ->and($failingValidator->fails())->toBeTrue()
        ->and($failingValidator->errors()->first('invoice'))->toContain('does not match extracted total')
        ->and($missingTotalsValidator->fails())->toBeTrue()
        ->and($nonArrayValidator->fails())->toBeTrue()
        ->and($nonArrayValidator->errors()->first('invoice'))->toContain('must be an array');
});

it('binds RateCardQuoteGuard and ConstrainedCatalogGuard with configured defaults for direct DI', function (): void {
    config()->set('ai-guardrails.currency_symbol', '£');
    config()->set('ai-guardrails.rate_cards', [
        'default' => [
            'audit' => ['label' => 'Audit', 'base' => 200, 'per_unit' => 50, 'unit_label' => 'day(s)', 'default_units' => 2],
        ],
    ]);
    app()->forgetInstance(AiGuardrailsManager::class);

    $quoteGuard = app(RateCardQuoteGuard::class);
    $catalogGuard = app(ConstrainedCatalogGuard::class);

    $quote = $quoteGuard->calculate(['service_category' => 'audit']);
    $catalog = $catalogGuard->enforce(
        [['id' => 'p_1', 'name' => 'Expensive', 'price' => 200, 'in_stock' => true]],
        ['applied_filters' => ['max_budget' => 100], 'matched_items' => [['item_id' => 'p_1']]],
    );

    expect($quote->currencySymbol)->toBe('£')
        ->and($quote->midpointAmount)->toBe(300)
        ->and($catalog->excludedItems[0]['reason'])->toContain('£200.00 exceeds £100.00 cap');
});

it('registers groundAiMatches and enforceAiCatalog Laravel Collection macros using the active container', function (): void {
    config()->set('ai-guardrails.currency_symbol', '€');
    app()->forgetInstance(AiGuardrailsManager::class);

    $accounts = collect([
        ['id' => 10, 'name' => 'Acme Corp', 'mrr' => 2500],
        ['id' => 20, 'name' => 'Beta Ltd', 'mrr' => 900],
    ]);

    $grounded = $accounts->groundAiMatches([
        ['id' => 10, 'mrr' => 0, 'match_reason' => 'High MRR account'],
        ['id' => 999, 'match_reason' => 'Hallucinated ID'],
    ]);

    $products = collect([
        ['id' => 'p_1', 'name' => 'Pro Plan', 'price' => 49.00, 'in_stock' => true, 'tags' => ['sso']],
        ['id' => 'p_2', 'name' => 'Enterprise Plan', 'price' => 199.00, 'in_stock' => true, 'tags' => ['sso']],
    ]);

    $catalog = $products->enforceAiCatalog([
        'applied_filters' => ['max_budget' => 100, 'require_in_stock' => true, 'required_tags' => ['sso']],
        'matched_items' => [
            ['item_id' => 'p_1', 'match_reason' => 'Under budget'],
            ['item_id' => 'p_2', 'match_reason' => 'Over budget'],
        ],
    ]);

    expect($grounded->verifiedCount)->toBe(1)
        ->and($grounded->strippedCount)->toBe(1)
        ->and($grounded->items[0]['mrr'])->toBe(2500)
        ->and($catalog->verifiedCount)->toBe(1)
        ->and($catalog->blockedCount)->toBe(1)
        ->and($catalog->items[0]['id'])->toBe('p_1')
        ->and($catalog->excludedItems[0]['reason'])->toContain('€199.00 exceeds €100.00 cap');
});
