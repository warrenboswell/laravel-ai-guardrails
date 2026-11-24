<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WarrenBoswell\LaravelAiGuardrails\AiGuardrailsServiceProvider;
use WarrenBoswell\LaravelAiGuardrails\Facades\AiGuardrails;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiGuardrailsServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'AiGuardrails' => AiGuardrails::class,
        ];
    }
}
