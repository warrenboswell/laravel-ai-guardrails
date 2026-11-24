<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Rules;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use WarrenBoswell\LaravelAiGuardrails\Guards\ArithmeticGuard;

readonly class VerifiedArithmetic implements ValidationRule
{
    public function __construct(
        private ?float $tolerance = null,
        private bool $requireTotals = false,
        private ArithmeticGuard $guard = new ArithmeticGuard,
    ) {}

    /**
     * Validate that an AI-extracted financial payload balances deterministically.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     *
     * @throws BindingResolutionException
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail("The $attribute must be an array of extracted financial fields.");

            return;
        }

        $tolerance = $this->resolveTolerance();
        $result = $this->guard->verify($value, $tolerance);

        if ($result->hasMismatch()) {
            $fail($result->detail);

            return;
        }

        if ($this->requireTotals && $result->status === 'not_applicable') {
            $fail("The $attribute is missing required financial totals for arithmetic verification.");
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function resolveTolerance(): float
    {
        if ($this->tolerance !== null) {
            return $this->tolerance;
        }

        $container = Container::getInstance();

        if ($container->bound('config')) {
            /** @var mixed $configured */
            $configured = $container->make('config')->get('ai-guardrails.arithmetic_tolerance', 0.02);

            if (is_numeric($configured)) {
                return (float) $configured;
            }
        }

        return 0.02;
    }
}
