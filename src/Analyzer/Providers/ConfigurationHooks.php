<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Providers;

use Closure;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use MagoPest\Analyzer\TestSuite;
use Override;
use Pest\PendingCalls\UsesCall;

/**
 * Binds `$this` in the `beforeEach()` and `afterEach()` hooks of `pest()` and
 * `uses()` to the test case Pest runs them on.
 *
 * @internal
 */
final readonly class ConfigurationHooks implements CallableSignatureOverride, MethodReturnTypeProvider
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function getTargets(): array
    {
        return [
            MethodTarget::exact(UsesCall::class, 'beforeEach'),
            MethodTarget::exact(UsesCall::class, 'afterEach'),
        ];
    }

    #[Override]
    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $testCase = $this->suite->classBoundTo($context->invocation->arguments);

        return (
            $testCase === null
                ? null
                : new EffectiveCallableSignature([
                    new CallableParameter(
                        name: '$hook',
                        type: Type::namedObject(Closure::class),
                        closureThisType: Type::namedObject($testCase),
                    ),
                ])
        );
    }

    #[Override]
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }
}
