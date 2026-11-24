<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

use WarrenBoswell\LaravelAiGuardrails\Results\ArithmeticAuditResult;
use WarrenBoswell\LaravelAiGuardrails\Support\NumberParser;

class ArithmeticGuard
{
    /**
     * Perform deterministic PHP arithmetic validation against LLM-extracted financial totals and line items.
     *
     * @param  array<string, mixed>  $extracted
     */
    public function verify(array $extracted, float $tolerance = 0.02): ArithmeticAuditResult
    {
        $subtotal = NumberParser::toFloat($extracted['subtotal'] ?? null);
        $discount = NumberParser::toFloat($extracted['discount_amount'] ?? null);
        $tax = NumberParser::toFloat($extracted['tax_amount'] ?? null);
        $total = NumberParser::toFloat($extracted['total_amount'] ?? null);
        $currency = is_string($extracted['currency'] ?? null) && $extracted['currency'] !== ''
           ? strtoupper($extracted['currency'])
           : '';

        if ($total === null || ($subtotal === null && $tax === null)) {
            return new ArithmeticAuditResult(
                status: 'not_applicable',
                headline: 'DTO Schema Validated',
                detail: 'No multi-line financial ledger totals detected; JSON types verified cleanly in PHP.',
            );
        }

        $effectiveSubtotal = $subtotal ?? 0.0;
        $effectiveDiscount = $discount ?? 0.0;
        $effectiveTax = $tax ?? 0.0;
        $computedTotal = round($effectiveSubtotal - $effectiveDiscount + $effectiveTax, 2);
        $prefix = $currency !== '' ? $currency.' ' : '';

        $lineMathMismatch = $this->detectLineItemMultiplicationMismatch(
            $extracted['line_items'] ?? null,
            $tolerance,
        );

        if ($lineMathMismatch !== null) {
            return new ArithmeticAuditResult(
                status: 'mismatch',
                headline: 'Line-Item Multiplication Drift Caught by PHP',
                detail: sprintf(
                    'Line item "%s" calculates %s × %s%s = %s%s, which does not match extracted line_total %s%s.',
                    $lineMathMismatch['label'],
                    rtrim(rtrim(number_format($lineMathMismatch['quantity'], 4, '.', ''), '0'), '.'),
                    $prefix,
                    number_format($lineMathMismatch['unit_price'], 2),
                    $prefix,
                    number_format($lineMathMismatch['expected'], 2),
                    $prefix,
                    number_format($lineMathMismatch['actual'], 2),
                ),
                computedTotal: $computedTotal,
                extractedTotal: $total,
                discrepancy: $lineMathMismatch['discrepancy'],
            );
        }

        $lineItemsAudit = $this->auditLineItemsSubtotal(
            $extracted['line_items'] ?? null,
            $subtotal,
            $tolerance,
        );

        $totalDelta = round(abs($computedTotal - $total), 2);

        if ($totalDelta > $tolerance) {
            $formulaDetail = $effectiveDiscount !== 0.0
               ? sprintf(
                   'Subtotal (%s%s) - Discount (%s%s) + Tax (%s%s) = %s%s, which does not match extracted total %s%s.',
                   $prefix,
                   number_format($effectiveSubtotal, 2),
                   $prefix,
                   number_format($effectiveDiscount, 2),
                   $prefix,
                   number_format($effectiveTax, 2),
                   $prefix,
                   number_format($computedTotal, 2),
                   $prefix,
                   number_format($total, 2),
               )
               : sprintf(
                   'Subtotal (%s%s) + Tax (%s%s) = %s%s, which does not match extracted total %s%s.',
                   $prefix,
                   number_format($effectiveSubtotal, 2),
                   $prefix,
                   number_format($effectiveTax, 2),
                   $prefix,
                   number_format($computedTotal, 2),
                   $prefix,
                   number_format($total, 2),
               );

            return new ArithmeticAuditResult(
                status: 'mismatch',
                headline: 'Arithmetic Mismatch Caught by PHP',
                detail: $formulaDetail,
                computedTotal: $computedTotal,
                extractedTotal: $total,
                lineItemsSum: $lineItemsAudit['line_sum'],
                discrepancy: $totalDelta,
            );
        }

        if ($lineItemsAudit['mismatch']) {
            $lineDelta = round(abs(($lineItemsAudit['line_sum'] ?? 0.0) - ($lineItemsAudit['subtotal'] ?? 0.0)), 2);

            return new ArithmeticAuditResult(
                status: 'mismatch',
                headline: 'Line-Item Drift Caught by PHP',
                detail: sprintf(
                    'Line items sum to %s%s, whereas extracted subtotal is %s%s.',
                    $prefix,
                    number_format($lineItemsAudit['line_sum'] ?? 0.0, 2),
                    $prefix,
                    number_format($lineItemsAudit['subtotal'] ?? 0.0, 2),
                ),
                computedTotal: $computedTotal,
                extractedTotal: $total,
                lineItemsSum: $lineItemsAudit['line_sum'],
                discrepancy: $lineDelta,
            );
        }

        $verifiedDetail = $effectiveDiscount !== 0.0
           ? sprintf(
               '%s%s - %s%s + %s%s = %s%s verified deterministically.',
               $prefix,
               number_format($effectiveSubtotal, 2),
               $prefix,
               number_format($effectiveDiscount, 2),
               $prefix,
               number_format($effectiveTax, 2),
               $prefix,
               number_format($total, 2),
           )
           : sprintf(
               '%s%s + %s%s = %s%s verified deterministically.',
               $prefix,
               number_format($effectiveSubtotal, 2),
               $prefix,
               number_format($effectiveTax, 2),
               $prefix,
               number_format($total, 2),
           );

        return new ArithmeticAuditResult(
            status: 'verified',
            headline: 'PHP Arithmetic Check Passed',
            detail: $verifiedDetail,
            computedTotal: $computedTotal,
            extractedTotal: $total,
            lineItemsSum: $lineItemsAudit['line_sum'],
            discrepancy: 0.0,
        );
    }

    /**
     * @return array{label: string, quantity: float, unit_price: float, expected: float, actual: float, discrepancy: float}|null
     */
    private function detectLineItemMultiplicationMismatch(mixed $lineItems, float $tolerance): ?array
    {
        if (! is_array($lineItems) || $lineItems === []) {
            return null;
        }

        foreach ($lineItems as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = NumberParser::toFloat($item['quantity'] ?? null, 4);
            $unitPrice = NumberParser::toFloat($item['unit_price'] ?? null, 4);
            $lineTotal = NumberParser::toFloat($item['line_total'] ?? null);

            if ($quantity === null || $unitPrice === null || $lineTotal === null) {
                continue;
            }

            $expected = round($quantity * $unitPrice, 2);
            $discrepancy = round(abs($expected - $lineTotal), 2);

            if ($discrepancy > $tolerance) {
                $label = is_string($item['description'] ?? null) && $item['description'] !== ''
                   ? $item['description']
                   : '#'.((int) $index + 1);

                return [
                    'label' => $label,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'expected' => $expected,
                    'actual' => $lineTotal,
                    'discrepancy' => $discrepancy,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{mismatch: bool, line_sum: float|null, subtotal: float|null}
     */
    private function auditLineItemsSubtotal(mixed $lineItems, ?float $subtotal, float $tolerance): array
    {
        if (! is_array($lineItems) || $lineItems === []) {
            return ['mismatch' => false, 'line_sum' => null, 'subtotal' => $subtotal];
        }

        $sum = 0.0;
        $hasLineTotal = false;

        foreach ($lineItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lineTotal = NumberParser::toFloat($item['line_total'] ?? null);

            if ($lineTotal !== null) {
                $sum += $lineTotal;
                $hasLineTotal = true;
            }
        }

        if (! $hasLineTotal) {
            return ['mismatch' => false, 'line_sum' => null, 'subtotal' => $subtotal];
        }

        $roundedSum = round($sum, 2);
        $mismatch = $subtotal !== null && abs($roundedSum - $subtotal) > $tolerance;

        return [
            'mismatch' => $mismatch,
            'line_sum' => $roundedSum,
            'subtotal' => $subtotal,
        ];
    }
}
