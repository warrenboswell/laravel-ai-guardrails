# Changelog

All notable changes to `warrenboswell/laravel-ai-guardrails` will be documented in this file.

## v1.0.0 - 2026-11-24

- Initial release of `warrenboswell/laravel-ai-guardrails` for PHP 8.2+ and Laravel 11, 12, and 13.
- **`PromptBoundaryGuard`**: Strip spoofed opening, closing, and self-closing XML/HTML boundary tags before wrapping untrusted prompt inputs.
- **`PiiRedactionGuard`**: Mask outbound API keys, Luhn-verified payment card numbers, emails, phone numbers, and IPv4 addresses into reversible numbered placeholders (`[EMAIL_1]`, `[CREDIT_CARD_1]`) with `->rehydrate()` support.
- **`ArithmeticGuard` & `VerifiedArithmetic` Validation Rule**: Deterministically audit LLM-extracted invoice line-item multiplication (`quantity * unit_price == line_total`), line-item sums, discounts, tax, and grand totals.
- **`CanonicalRecordGuard` & `Collection::groundAiMatches()` Macro**: Re-hydrate AI-selected record IDs against canonical Eloquent `Collection`s or arrays while stripping hallucinated and duplicate IDs.
- **`RateCardQuoteGuard`**: Compute deterministic price ranges and SLA tiers from fixed PHP rate cards.
- **`ConstrainedCatalogGuard` & `Collection::enforceAiCatalog()` Macro**: Enforce hard budget ceilings, stock availability, required tags, and canonical catalog IDs on AI product recommendations.
- **Self-Correction Prompts (`->toRetryPrompt()`)**: Generate deterministic retry instructions from `ArithmeticAuditResult`, `CanonicalGroundResult`, and `CatalogGuardResult` when verification fails.
