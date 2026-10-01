<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Providers;

use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use MagoPest\Analyzer\ExpectationTypes;
use MagoPest\Analyzer\TestSuite;
use Override;
use Pest\Expectation;
use Pest\Expectations\HigherOrderExpectation;

/**
 * Types property reads on expectations, which Pest resolves through `__get`.
 * A matcher name calls the matcher, and any other name reads that property
 * of the expectation's value and returns a higher-order expectation.
 *
 * @internal
 */
final readonly class ExpectationProperties implements PropertyTypeProvider
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function getTargets(): array
    {
        return [
            PropertyTarget::allProperties(Expectation::class),
            PropertyTarget::allProperties(HigherOrderExpectation::class),
        ];
    }

    #[Override]
    public function getPropertyType(PropertyTypeProviderContext $context): ?PropertyType
    {
        $access = $context->access;
        $codebase = $context->codebase;
        $isDeclared =
            $codebase->propertyExists($access->class, $access->property)
            || $codebase->magicPropertyExists($access->class, $access->property);
        $type = $this->suite->isMatcher($access->property, $codebase)
            ? ExpectationTypes::afterMatcher($access->receiverType)
            : ExpectationTypes::afterMember($access->receiverType);

        return $isDeclared || $type === null ? null : new PropertyType($type);
    }
}
