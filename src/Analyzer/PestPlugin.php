<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;
use MagoPest\Analyzer\Hooks\TestCaseClasses;
use MagoPest\Analyzer\Providers\ConfigurationHooks;
use MagoPest\Analyzer\Providers\ExpectationCalls;
use MagoPest\Analyzer\Providers\ExpectationProperties;
use MagoPest\Analyzer\Providers\TestCaseProperties;
use MagoPest\Analyzer\Providers\TestFunctions;
use Override;

/**
 * @internal
 */
final readonly class PestPlugin implements Plugin
{
    /**
     * @param non-empty-string $testDirectory
     */
    public function __construct(
        private string $testDirectory,
    ) {}

    #[Override]
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'pest',
            name: 'Pest',
            description: 'Types Pest test closures, their dynamic properties, and expectation chains.',
        );
    }

    #[Override]
    public function register(PluginRegistry $registry): void
    {
        $suite = new TestSuite($this->testDirectory);

        $registry->registerInitializationHook(new TestCaseClasses($suite));
        $registry->registerFunctionReturnTypeProvider(new TestFunctions($suite));
        $registry->registerMethodReturnTypeProvider(new ConfigurationHooks($suite));
        $registry->registerPropertyTypeProvider(new TestCaseProperties($suite));
        $registry->registerMethodReturnTypeProvider(new ExpectationCalls($suite));
        $registry->registerPropertyTypeProvider(new ExpectationProperties($suite));
    }
}
