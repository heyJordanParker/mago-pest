<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Providers;

use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use MagoPest\Analyzer\TestSuite;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Types the dynamic properties a generated test case declares by the values
 * its closures assign. Writes accept any value, as Pest's dynamic properties do.
 *
 * @internal
 */
final readonly class TestCaseProperties implements PropertyTypeProvider
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function getTargets(): array
    {
        return [PropertyTarget::allProperties(TestCase::class)];
    }

    #[Override]
    public function getPropertyType(PropertyTypeProviderContext $context): ?PropertyType
    {
        $type = $this->suite->propertyType($context->access->class, $context->access->property, $context->codebase);

        return $type === null ? null : new PropertyType($type, Type::mixed());
    }
}
