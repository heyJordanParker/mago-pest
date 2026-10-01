<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

/**
 * One `pest()` or `uses()` declaration: the classes and traits it adds, the
 * test files it applies to, and the hooks it runs on their test cases.
 *
 * @internal
 */
final readonly class Configuration
{
    private const array EXTEND_METHODS = ['extend', 'extends', 'use', 'uses'];

    private const array HOOK_METHODS = ['beforeeach', 'aftereach'];

    /**
     * @param list<string> $classes Classes and traits, fully qualified, in declaration order.
     * @param list<BoundClosure> $hooks
     */
    private function __construct(
        public string $path,
        public int $offset,
        public array $classes,
        public Targets $targets,
        public array $hooks,
    ) {}

    /**
     * Follows the `pest()` or `uses()` call at `$index` through its method chain,
     * the way Pest's `Configuration` and `UsesCall` accumulate classes, targets, and hooks.
     */
    public static function read(Tokens $tokens, int $index, Names $names): self
    {
        $function = $tokens->functionCall($index);
        [$arguments, $next] = $tokens->arguments($index + 1);
        $classes = $function === 'uses' ? self::classes($tokens, $arguments, $names) : [];
        $targets = [];
        $hooks = [];

        while ($tokens->is($next, T_OBJECT_OPERATOR) && $tokens->is($next + 2, ord('('))) {
            $method = strtolower($tokens->tokens[$next + 1]->text);
            [$arguments, $next] = $tokens->arguments($next + 2);
            array_push(
                $classes,
                ...in_array($method, self::EXTEND_METHODS, true) ? self::classes($tokens, $arguments, $names) : [],
            );
            array_push($targets, ...$method === 'in' ? $arguments : []);
            array_push(
                $hooks,
                ...in_array($method, self::HOOK_METHODS, true)
                    ? BoundClosure::inArguments($tokens, $arguments, $names)
                    : [],
            );
        }

        $isGlobal = $function === 'pest' && basename($tokens->path) === 'Pest.php';

        return new self(
            $tokens->path,
            $tokens->tokens[$index]->pos,
            $classes,
            Targets::read($tokens, $targets, $isGlobal ? dirname($tokens->path) : $tokens->path),
            $hooks,
        );
    }

    /**
     * @param list<array{int, int}> $arguments
     * @return list<string>
     */
    private static function classes(Tokens $tokens, array $arguments, Names $names): array
    {
        $classes = [];
        foreach ($arguments as [$first, $last]) {
            $classes[] = match (true) {
                $last === ($first + 2) && $tokens->is($last, 'class') => $names->resolve($tokens->tokens[$first]),
                $first === $last && $tokens->is($first, T_CONSTANT_ENCAPSED_STRING) => ltrim(
                    stripslashes(substr($tokens->tokens[$first]->text, 1, -1)),
                    '\\',
                ),
                default => null,
            };
        }

        return array_values(array_filter($classes, static fn(?string $class): bool => $class !== null));
    }
}
