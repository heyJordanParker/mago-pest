<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\FunctionLikeKind;

/**
 * A value whose type a declaration states: a closure, an instantiation, or one
 * call to a declared function or method. A method called on `$this` is named
 * on `static`, the test case the closure runs on.
 *
 * @internal
 */
final readonly class DeclaredValue
{
    private const array CLASS_NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private const array FUNCTION_NAMES = [T_STRING, T_NAME_FULLY_QUALIFIED];

    private const array RELATIVE_CLASSES = ['self', 'static', 'parent'];

    /**
     * @return array{string, Type|FunctionLikeIdentifier}|null The type's key and the type, or null for no declared type.
     */
    public static function type(Tokens $tokens, int $first, int $last, Names $names): ?array
    {
        $identifier = $tokens->isClosure($first)
            ? new FunctionLikeIdentifier(FunctionLikeKind::Closure, $tokens->closureName($first))
            : self::call($tokens, $first, $last, $names);
        $class = self::instantiated($tokens, $first, $last, $names);

        return match (true) {
            $identifier !== null => [
                "{$identifier->kind->name} {$identifier->class}::{$identifier->name}",
                $identifier,
            ],
            $class !== null => ["new {$class}", Type::namedObject($class)],
            default => null,
        };
    }

    private static function call(Tokens $tokens, int $first, int $last, Names $names): ?FunctionLikeIdentifier
    {
        $token = $tokens->tokens[$first];

        return match (true) {
            $token->is(self::FUNCTION_NAMES) && self::isCall($tokens, $first + 1, $last)
                => new FunctionLikeIdentifier(FunctionLikeKind::Function_, ltrim($token->text, '\\')),
            !$tokens->is($first + 2, T_STRING) || !self::isCall($tokens, $first + 3, $last) => null,
            $token->text === '$this' && $tokens->is($first + 1, T_OBJECT_OPERATOR) => new FunctionLikeIdentifier(
                FunctionLikeKind::Method,
                $tokens->tokens[$first + 2]->text,
                'static',
            ),
            $tokens->is($first + 1, T_DOUBLE_COLON) && self::isNamedClass($tokens, $first)
                => new FunctionLikeIdentifier(
                FunctionLikeKind::Method,
                $tokens->tokens[$first + 2]->text,
                $names->resolve($token),
            ),
            default => null,
        };
    }

    private static function instantiated(Tokens $tokens, int $first, int $last, Names $names): ?string
    {
        $isInstantiation =
            $tokens->is($first, T_NEW)
            && $tokens->is($first + 1, self::CLASS_NAMES)
            && self::isCall($tokens, $first + 2, $last);

        return $isInstantiation ? $names->resolve($tokens->tokens[$first + 1]) : null;
    }

    private static function isNamedClass(Tokens $tokens, int $index): bool
    {
        return (
            $tokens->is($index, self::CLASS_NAMES)
            && !in_array(strtolower($tokens->tokens[$index]->text), self::RELATIVE_CLASSES, true)
        );
    }

    /**
     * True when the tokens from `$open` to `$last` are exactly one argument list.
     */
    private static function isCall(Tokens $tokens, int $open, int $last): bool
    {
        return $tokens->is($open, ord('(')) && $tokens->arguments($open)[1] === ($last + 1);
    }
}
