<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Pest\Expectation;
use Pest\Expectations\HigherOrderExpectation;
use Pest\Expectations\OppositeExpectation;
use Pest\Mixins;

/**
 * The expectation Pest returns from a matcher or a member read on an expectation.
 *
 * @internal
 */
final readonly class ExpectationTypes
{
    private const array HIGHER_ORDER_RECEIVERS = [Expectation::class, HigherOrderExpectation::class];

    /**
     * A matcher returns the expectation it was called on, and `not` hands back the original one.
     */
    public static function afterMatcher(?Type $receiver): ?Type
    {
        $object = self::expectation($receiver);

        return match ($object?->name) {
            null, Mixins\Expectation::class => null,
            OppositeExpectation::class => Type::namedObject(Expectation::class, ...$object->parameters ?? []),
            default => $receiver,
        };
    }

    /**
     * A member read on an expectation's value returns `HigherOrderExpectation<TOriginalValue, mixed>`:
     * the original value stays known, and the member's value is whatever the value returns.
     */
    public static function afterMember(?Type $receiver): ?Type
    {
        $object = self::expectation($receiver);

        return in_array($object?->name, self::HIGHER_ORDER_RECEIVERS, true)
            ? Type::namedObject(HigherOrderExpectation::class, $object->parameters[0] ?? Type::mixed(), Type::mixed())
            : null;
    }

    private static function expectation(?Type $receiver): ?NamedObjectType
    {
        $atomic = $receiver?->atomicTypes;

        return $atomic !== null && count($atomic) === 1 && $atomic[0] instanceof NamedObjectType ? $atomic[0] : null;
    }
}
