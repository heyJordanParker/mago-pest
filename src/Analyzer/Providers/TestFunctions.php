<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Providers;

use Closure;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\FunctionReturnTypeProvider;
use Mago\Sdk\Analyzer\FunctionTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use MagoPest\Analyzer\TestSuite;
use Override;

/**
 * Binds `$this` in the closures of `test()`, `it()`, `beforeEach()`, and
 * `afterEach()` to the test case Pest runs them on.
 *
 * @internal
 */
final readonly class TestFunctions implements CallableSignatureOverride, FunctionReturnTypeProvider
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function getTargets(): array
    {
        return [
            FunctionTarget::exact('test'),
            FunctionTarget::exact('it'),
            FunctionTarget::exact('beforeEach'),
            FunctionTarget::exact('afterEach'),
        ];
    }

    #[Override]
    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $testCase = $this->suite->classBoundTo($context->invocation->arguments);
        if ($testCase === null) {
            return null;
        }

        $closure = new CallableParameter(
            name: '$closure',
            type: Type::union(Type::namedObject(Closure::class), Type::null()),
            closureThisType: Type::namedObject($testCase),
            hasDefault: true,
        );

        return new EffectiveCallableSignature(match (strtolower($context->invocation->name)) {
            'test' => [
                new CallableParameter('$description', Type::union(Type::string(), Type::null()), hasDefault: true),
                $closure,
            ],
            'it' => [new CallableParameter('$description', Type::string()), $closure],
            default => [$closure],
        });
    }

    #[Override]
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }
}
