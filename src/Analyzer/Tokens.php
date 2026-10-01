<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use PhpToken;

/**
 * One PHP file as its tokens, without whitespace or comments.
 *
 * Test files are read from PHP's tokens because Mago parses source only after
 * initialization, and the test case classes must be declared before that.
 *
 * @internal
 */
final readonly class Tokens
{
    /** How each token changes the bracket depth. A bracket inside a string's text is that text. */
    public const array DEPTH = [
        40 => 1,
        91 => 1,
        123 => 1,
        T_CURLY_OPEN => 1,
        T_DOLLAR_OPEN_CURLY_BRACES => 1,
        T_ATTRIBUTE => 1,
        41 => -1,
        93 => -1,
        125 => -1,
    ];

    private const array CALL_PREFIXES = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    private const array FUNCTION_NAMES = [T_STRING, T_NAME_FULLY_QUALIFIED];

    private const array CLOSURE_KEYWORDS = [T_FUNCTION, T_FN];

    /**
     * @param list<PhpToken> $tokens
     */
    private function __construct(
        public string $path,
        public string $contents,
        public array $tokens,
    ) {}

    public static function of(string $path, string $contents): self
    {
        return new self(
            $path,
            $contents,
            array_values(array_filter(
                PhpToken::tokenize($contents),
                static fn(PhpToken $lexeme): bool => !$lexeme->isIgnorable(),
            )),
        );
    }

    /**
     * True when a token exists at `$index` and is `$kind`: a token id, a text, or a list of either.
     *
     * @param int|string|list<mixed> $kind
     */
    public function is(int $index, int|string|array $kind): bool
    {
        return array_key_exists($index, $this->tokens) && $this->tokens[$index]->is($kind);
    }

    /**
     * The lower-cased name of the function called at `$index`, or null when no plain function call starts there.
     */
    public function functionCall(int $index): ?string
    {
        $isCall =
            $this->is($index, self::FUNCTION_NAMES)
            && $this->is($index + 1, ord('('))
            && !$this->is($index - 1, self::CALL_PREFIXES);

        return $isCall ? strtolower(ltrim($this->tokens[$index]->text, '\\')) : null;
    }

    public function isClosure(int $index): bool
    {
        return (
            $this->is($index, self::CLOSURE_KEYWORDS)
            || $this->is($index, T_STATIC) && $this->is($index + 1, self::CLOSURE_KEYWORDS)
        );
    }

    /**
     * Mago's name for the closure starting at `$index`: `{closure:<path>:<line>:<column>}`, both 1-based.
     */
    public function closureName(int $index): string
    {
        $token = $this->tokens[$index];
        $column = $token->pos + 1 - (int) strrpos("\n" . substr($this->contents, 0, $token->pos), "\n");

        return "{closure:{$this->path}:{$token->line}:{$column}}";
    }

    /**
     * The exact source of the tokens from `$first` to `$last`.
     */
    public function source(int $first, int $last): string
    {
        $start = $this->tokens[$first]->pos;
        $end = $this->tokens[$last]->pos + strlen($this->tokens[$last]->text);

        return substr($this->contents, $start, $end - $start);
    }

    /**
     * Splits the parenthesized arguments opening at `$open` on their top-level commas.
     *
     * @return array{list<array{int, int}>, int} Each argument's first and last token index, and the index after `)`.
     */
    public function arguments(int $open): array
    {
        $depth = 0;
        $boundaries = [$open];
        foreach (array_slice($this->tokens, $open, preserve_keys: true) as $index => $token) {
            $depth += self::DEPTH[$token->id] ?? 0;
            if ($depth === 0) {
                return [self::between([...$boundaries, $index]), $index + 1];
            }

            if ($depth === 1 && $token->id === ord(',')) {
                $boundaries[] = $index;
            }
        }

        return [[], count($this->tokens)];
    }

    /**
     * @param non-empty-list<int> $boundaries
     * @return list<array{int, int}> The non-empty runs of tokens between consecutive boundaries.
     */
    private static function between(array $boundaries): array
    {
        $runs = [];
        foreach (array_slice($boundaries, 1) as $position => $end) {
            $runs[] = [$boundaries[$position] + 1, $end - 1];
        }

        return array_values(array_filter($runs, static fn(array $run): bool => $run[0] <= $run[1]));
    }
}
