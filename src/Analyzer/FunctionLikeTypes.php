<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\CallableSignature;
use Mago\Sdk\Analyzer\Type\CallableType;
use Mago\Sdk\Analyzer\Type\ConditionalType;
use Mago\Sdk\Analyzer\Type\DerivedType;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\FunctionLikeKind;
use Mago\Sdk\Analyzer\Type\GenericParameterType;
use Mago\Sdk\Analyzer\Type\VariableType;

/**
 * The types a function-like declares: a closure's own type, a function's or
 * method's return type, and its parameters.
 *
 * @internal
 */
final readonly class FunctionLikeTypes
{
    /** The atomic types that resolve only from the arguments of a call. */
    private const array CALL_DEPENDENT = [
        GenericParameterType::class,
        ConditionalType::class,
        DerivedType::class,
        VariableType::class,
    ];

    /**
     * A closure's own type, or the type a function or method declares it returns.
     * `static` names `$testCase`, the class the closure runs on. A return type
     * that depends on the call's arguments resolves only at the call, so it is mixed here.
     */
    public static function valueOf(FunctionLikeIdentifier $identifier, string $testCase, Codebase $codebase): Type
    {
        $metadata = match ($identifier->kind) {
            FunctionLikeKind::Method => $codebase->getDeclaringMethod(
                $identifier->class === 'static' ? $testCase : (string) $identifier->class,
                $identifier->name,
            ),
            default => $codebase->getFunctionLike($identifier),
        };

        return match (true) {
            $metadata === null => Type::mixed(),
            $identifier->kind === FunctionLikeKind::Closure => self::closure($identifier, $metadata),
            default => self::declaredReturn($metadata),
        };
    }

    /**
     * @return list<CallableParameter>
     */
    public static function parameters(FunctionLikeMetadata $metadata): array
    {
        return array_map(
            static fn(ParameterMetadata $parameter): CallableParameter => new CallableParameter(
                name: $parameter->name,
                type: $parameter->type?->type,
                byReference: $parameter->flags->contains(MetadataFlags::BY_REFERENCE),
                variadic: $parameter->flags->contains(MetadataFlags::VARIADIC),
                hasDefault: $parameter->flags->contains(MetadataFlags::HAS_DEFAULT),
            ),
            $metadata->parameters,
        );
    }

    private static function closure(FunctionLikeIdentifier $identifier, FunctionLikeMetadata $metadata): Type
    {
        return Type::fromAtomic(
            new CallableType(
                new CallableSignature(
                    pure: false,
                    closure: true,
                    parameters: self::parameters($metadata),
                    returnType: $metadata->returnType?->type,
                    source: $identifier,
                    constraints: [],
                ),
                null,
            ),
        );
    }

    private static function declaredReturn(FunctionLikeMetadata $metadata): Type
    {
        $type = $metadata->returnType?->type;
        if ($type === null || $metadata->templates !== []) {
            return Type::mixed();
        }

        foreach ($type->atomicTypes as $atomic) {
            if (in_array($atomic::class, self::CALL_DEPENDENT, true)) {
                return Type::mixed();
            }
        }

        return $type;
    }
}
