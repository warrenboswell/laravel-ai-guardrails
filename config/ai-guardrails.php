<?php

declare(strict_types=1);

return [

    /*
   |--------------------------------------------------------------------------
   | Default Untrusted Prompt Boundary Tag
   |--------------------------------------------------------------------------
   |
   | When wrapping untrusted user or document text before passing it to an LLM,
   | PromptBoundaryGuard strips any spoofed instances of this XML/HTML tag
   | from the payload and encloses the text inside <tag>...</tag>.
   |
   */
    'boundary_tag' => env('AI_GUARDRAILS_BOUNDARY_TAG', 'sandbox_input'),

    /*
   |--------------------------------------------------------------------------
   | Arithmetic Verification Tolerance
   |--------------------------------------------------------------------------
   |
   | Maximum allowed floating-point rounding delta when verifying invoice
   | line-item math, subtotals, discounts, taxes, and grand totals.
   |
   */
    'arithmetic_tolerance' => (float) env('AI_GUARDRAILS_ARITHMETIC_TOLERANCE', 0.02),

    /*
   |--------------------------------------------------------------------------
   | Default Currency Symbol
   |--------------------------------------------------------------------------
   |
   | Used when formatting human-readable rate-card formula breakdowns and
   | catalog exclusion messages (for example "$", "£", or "€").
   |
   */
    'currency_symbol' => env('AI_GUARDRAILS_CURRENCY_SYMBOL', '$'),

    /*
   |--------------------------------------------------------------------------
   | Named Rate Cards
   |--------------------------------------------------------------------------
   |
   | Optional rate cards used by RateCardQuoteGuard when no inline $rateCard
   | array is passed at runtime. Define a 'default' key or named cards that
   | you can reference via AiGuardrails::calculateQuote(..., rateCardName: '...').
   |
   | Example:
   | 'default' => [
   |     'consulting' => [
   |         'label' => 'Advisory & Scoping Sprint',
   |         'base' => 500,
   |         'per_unit' => 150,
   |         'unit_label' => 'hour(s)',
   |         'default_units' => 4,
   |     ],
   | ],
   |
   */
    'rate_cards' => [],

];
