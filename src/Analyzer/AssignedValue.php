<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;

/**
 * The type an assignment to `$this->property` gives the property, read from
 * the value's tokens.
 *
 * A value is typed only when its tokens state the type: a literal, a string,
 * an array, a cast, or a value a declaration types. Any other value is mixed,
 * since typing it would take Mago's inference.
 *
 * @internal
 */
final readonly class AssignedValue
{
    private const array LITERALS = [
        T_CONSTANT_ENCAPSED_STRING => 'string',
        T_LNUMBER => 'int',
        T_DNUMBER => 'float',
    ];

    private const array KEYWORDS = ['true' => 'bool', 'false' => 'bool', 'null' => 'null'];

    private const array OPENERS = [
        34 => 'string',
        91 => 'array',
        T_ARRAY => 'array',
        T_INT_CAST => 'int',
        T_DOUBLE_CAST => 'float',
        T_STRING_CAST => 'string',
        T_BOOL_CAST => 'bool',
        T_ARRAY_CAST => 'array',
    ];

    private const array BINARY_OPERATORS = [
        '+',
        '-',
        '*',
        '/',
        '%',
        '.',
        '?',
        ':',
        '<',
        '>',
        '|',
        '&',
        '^',
        '=',
        '**',
        '??',
        '?:',
        '<=>',
        '==',
        '!=',
        '===',
        '!==',
        '<=',
        '>=',
        '&&',
        '||',
        '<<',
        '>>',
        'and',
        'or',
        'xor',
        'instanceof',
    ];

    /**
     * @return array{string, Type|FunctionLikeIdentifier} The type's key, so equal types merge, and the type.
     */
    public static function type(Tokens $tokens, int $first, int $last, Names $names): array
    {
        $scalar = self::scalar($tokens, $first, $last);

        return (
            $scalar === null
                ? DeclaredValue::type($tokens, $first, $last, $names) ?? ['mixed', Type::mixed()]
                : [$scalar, self::scalarType($scalar)]
        );
    }

    /**
     * @return 'string'|'int'|'float'|'bool'|'null'|'array'|null
     */
    private static function scalar(Tokens $tokens, int $first, int $last): ?string
    {
        $token = $tokens->tokens[$first];
        if ($first === $last) {
            return self::LITERALS[$token->id] ?? self::KEYWORDS[strtolower($token->text)] ?? null;
        }

        return self::isOneOperand($tokens, $first, $last) ? self::OPENERS[$token->id] ?? null : null;
    }

    /**
     * @param 'string'|'int'|'float'|'bool'|'null'|'array' $scalar
     */
    private static function scalarType(string $scalar): Type
    {
        return match ($scalar) {
            'string' => Type::string(),
            'int' => Type::int(),
            'float' => Type::float(),
            'bool' => Type::bool(),
            'null' => Type::null(),
            'array' => Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
        };
    }

    /**
     * True when no binary operator joins a second operand at the top level.
     */
    private static function isOneOperand(Tokens $tokens, int $first, int $last): bool
    {
        $depth = 0;
        foreach (array_slice($tokens->tokens, $first, $last - $first + 1) as $token) {
            $depth += Tokens::DEPTH[$token->id] ?? 0;
            $isOperator =
                $depth === 0
                && !$token->is(T_ENCAPSED_AND_WHITESPACE)
                && in_array(strtolower($token->text), self::BINARY_OPERATORS, true);
            if ($isOperator) {
                return false;
            }
        }

        return true;
    }
}
