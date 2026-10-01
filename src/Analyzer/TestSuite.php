<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\MixedType;
use Pest\Mixins\Expectation;

/**
 * The closures Pest binds to each test case class, the properties those
 * closures assign to `$this`, and the matchers added with `expect()->extend()`.
 *
 * Mago names no file in a provider request, so each bound closure is found by
 * its offset and text, which the provider's argument carries.
 *
 * @internal
 */
final class TestSuite
{
    /** @var array<string, string> Closure offset and text => test case class. */
    private array $closures = [];

    /** @var array<string, array<string, array<string, Type|FunctionLikeIdentifier>>> Class => property => type key => type. */
    private array $properties = [];

    /** @var array<string, list<string>> Test case class => the hook classes whose properties it shares. */
    private array $hookClasses = [];

    /** @var array<string, FunctionLikeIdentifier> Lower-cased matcher name => its closure. */
    private array $matchers = [];

    /**
     * @param non-empty-string $directory Pest's test directory, relative to the Mago workspace.
     */
    public function __construct(
        public readonly string $directory,
    ) {}

    /**
     * @param list<BoundClosure> $closures
     * @param list<string> $hookClasses
     */
    public function declare(string $class, array $closures, array $hookClasses): void
    {
        $this->hookClasses[$class] = $hookClasses;
        foreach ($closures as $closure) {
            $this->closures[self::key($closure->start, $closure->text)] = $class;
            foreach ($closure->properties as $property => $types) {
                $this->properties[$class][$property] = [...($this->properties[$class][$property] ?? []), ...$types];
            }
        }
    }

    /**
     * @param array<string, FunctionLikeIdentifier> $matchers
     */
    public function addMatchers(array $matchers): void
    {
        foreach ($matchers as $name => $closure) {
            $this->matchers[strtolower($name)] = $closure;
        }
    }

    /**
     * @param list<Argument> $arguments
     */
    public function classBoundTo(array $arguments): ?string
    {
        foreach ($arguments as $argument) {
            $class = $this->closures[self::key($argument->span->start, $argument->expression)] ?? null;
            if ($class !== null) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The union of every value the property is assigned, in the test file and
     * in the hooks that run on its test case, or null when nothing assigns it.
     * One mixed value makes the property mixed.
     */
    public function propertyType(string $class, string $property, Codebase $codebase): ?Type
    {
        $assigned = [];
        foreach ([$class, ...($this->hookClasses[$class] ?? [])] as $owner) {
            $assigned = [...$assigned, ...($this->properties[$owner][$property] ?? [])];
        }

        $types = array_map(static fn(Type|FunctionLikeIdentifier $type): Type => $type instanceof Type
            ? $type
            : FunctionLikeTypes::valueOf($type, $class, $codebase), array_values($assigned));
        $atomics = array_merge(...array_map(static fn(Type $type): array => $type->atomicTypes, $types));

        return match (true) {
            $types === [] => null,
            array_filter($atomics, static fn(object $atomic): bool => $atomic instanceof MixedType) !== []
                => Type::mixed(),
            count($types) === 1 => $types[0],
            default => Type::union($types[0], $types[1], ...array_slice($types, 2)),
        };
    }

    /**
     * The closure `expect()->extend()` registers under `$name`, or null when no extension adds it.
     */
    public function matcher(string $name): ?FunctionLikeIdentifier
    {
        return $this->matchers[strtolower($name)] ?? null;
    }

    /**
     * True when Pest resolves `$name` on an expectation as a matcher rather than a member of its value.
     */
    public function isMatcher(string $name, Codebase $codebase): bool
    {
        return $codebase->methodExists(Expectation::class, $name) || $this->matcher($name) !== null;
    }

    private static function key(int $start, string $text): string
    {
        return $start . ':' . md5($text);
    }
}
