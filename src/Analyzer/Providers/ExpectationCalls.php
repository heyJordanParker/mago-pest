<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Providers;

use Closure;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use MagoPest\Analyzer\ExpectationTypes;
use MagoPest\Analyzer\FunctionLikeTypes;
use MagoPest\Analyzer\TestSuite;
use Override;
use Pest\Expectation;
use Pest\Expectations\HigherOrderExpectation;
use Pest\Mixins;
use Throwable;

/**
 * Types method calls on expectations, which Pest dispatches through `__call`.
 *
 * A matcher of `Pest\Mixins\Expectation` or of `expect()->extend()` returns
 * the expectation it was called on. Any other method calls that method on the
 * expectation's value and returns a higher-order expectation. `toThrow()` also
 * accepts a closure typed by the exception it expects, which Pest's docblock
 * narrows to `Closure(Throwable): mixed`.
 *
 * @internal
 */
final readonly class ExpectationCalls implements CallableSignatureOverride, MethodReturnTypeProvider
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function getTargets(): array
    {
        return [
            MethodTarget::allMethods(Mixins\Expectation::class),
            MethodTarget::allMethods(Expectation::class),
            MethodTarget::allMethods(HigherOrderExpectation::class),
        ];
    }

    #[Override]
    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $invocation = $context->invocation;
        $matcher = $this->suite->matcher($invocation->name);
        $metadata = $matcher === null ? null : $context->codebase->getFunctionLike($matcher);

        return match (true) {
            self::isToThrow($invocation) => self::toThrow(),
            $metadata !== null => new EffectiveCallableSignature(FunctionLikeTypes::parameters($metadata)),
            $this->isDeclared($invocation, $context->codebase) => null,
            default => new EffectiveCallableSignature([
                new CallableParameter('$arguments', Type::mixed(), variadic: true),
            ]),
        };
    }

    #[Override]
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $isMatcher =
            $invocation->declaringClass === Mixins\Expectation::class
            || $this->suite->matcher($invocation->name) !== null;

        return match (true) {
            $isMatcher => ExpectationTypes::afterMatcher($invocation->receiverType),
            $this->isDeclared($invocation, $context->codebase) => null,
            default => ExpectationTypes::afterMember($invocation->receiverType),
        };
    }

    private function isDeclared(Invocation $invocation, Codebase $codebase): bool
    {
        return (
            $this->suite->isMatcher($invocation->name, $codebase)
            || $codebase->methodExists((string) $invocation->declaringClass, $invocation->name)
        );
    }

    private static function isToThrow(Invocation $invocation): bool
    {
        return $invocation->declaringClass === Mixins\Expectation::class && strtolower($invocation->name) === 'tothrow';
    }

    private static function toThrow(): EffectiveCallableSignature
    {
        return new EffectiveCallableSignature([
            new CallableParameter('$exception', Type::union(
                Type::namedObject(Closure::class),
                Type::string(),
                Type::namedObject(Throwable::class),
            )),
            new CallableParameter('$exceptionMessage', Type::union(Type::string(), Type::null()), hasDefault: true),
            new CallableParameter('$message', Type::string(), hasDefault: true),
        ]);
    }
}
