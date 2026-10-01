<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;

/**
 * A closure Pest runs with `$this` bound to a test case, and the properties
 * it assigns to `$this`.
 *
 * @internal
 */
final readonly class BoundClosure
{
    private const array SEPARATORS = [59, 44];

    /**
     * @param array<string, array<string, Type|FunctionLikeIdentifier>> $properties Property => type key => type.
     */
    private function __construct(
        public int $start,
        public string $text,
        public array $properties,
    ) {}

    /**
     * The closures among the call arguments `$arguments`.
     *
     * @param list<array{int, int}> $arguments
     * @return list<self>
     */
    public static function inArguments(Tokens $tokens, array $arguments, Names $names): array
    {
        $closures = [];
        foreach ($arguments as [$first, $last]) {
            if (!$tokens->isClosure($first)) {
                continue;
            }

            $closures[] = self::read($tokens, $first, $last, $names);
        }

        return $closures;
    }

    private static function read(Tokens $tokens, int $first, int $last, Names $names): self
    {
        $properties = [];
        for ($index = $first; $index <= $last; ++$index) {
            if (!self::assignsProperty($tokens, $index)) {
                continue;
            }

            $value = $index + 4;
            [$key, $type] = AssignedValue::type($tokens, $value, self::end($tokens, $value, $last), $names);
            $properties[$tokens->tokens[$index + 2]->text][$key] = $type;
        }

        return new self($tokens->tokens[$first]->pos, $tokens->source($first, $last), $properties);
    }

    /**
     * True when `$this->property =` or `$this->property ??=` starts at `$index`.
     */
    private static function assignsProperty(Tokens $tokens, int $index): bool
    {
        return (
            $tokens->is($index, '$this')
            && $tokens->is($index + 1, T_OBJECT_OPERATOR)
            && $tokens->is($index + 2, T_STRING)
            && $tokens->is($index + 3, [ord('='), T_COALESCE_EQUAL])
        );
    }

    /**
     * The last token of the value starting at `$first`: the one before a top-level `;`, `,`, or closing bracket.
     */
    private static function end(Tokens $tokens, int $first, int $last): int
    {
        $depth = 0;
        foreach (array_slice($tokens->tokens, $first, $last - $first + 1, preserve_keys: true) as $index => $token) {
            $depth += Tokens::DEPTH[$token->id] ?? 0;
            if ($depth < 0 || $depth === 0 && in_array($token->id, self::SEPARATORS, true)) {
                return $index - 1;
            }
        }

        return $last;
    }
}
