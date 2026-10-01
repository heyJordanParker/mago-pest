<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

/**
 * The test files a `pest()` or `uses()` declaration applies to.
 *
 * @internal
 */
final readonly class Targets
{
    /**
     * @param list<string> $paths Workspace-relative files, folders, or globs.
     */
    private function __construct(
        private array $paths,
    ) {}

    /**
     * Resolves `in()` arguments relative to the declaring file's folder, as
     * `UsesCall::in()` does. Without `in()`, the declaration applies to `$default`.
     *
     * @param list<array{int, int}> $arguments
     */
    public static function read(Tokens $tokens, array $arguments, string $default): self
    {
        $folder = dirname($tokens->path);
        $paths = [];
        foreach ($arguments as [$first, $last]) {
            $paths[] = match (true) {
                $first !== $last => null,
                $tokens->is($first, T_DIR) => $folder,
                $tokens->is($first, T_CONSTANT_ENCAPSED_STRING) => self::normalize(
                    $folder . '/' . substr($tokens->tokens[$first]->text, 1, -1),
                ),
                default => null,
            };
        }

        return new self($arguments === [] ? [$default] : array_values(array_filter($paths, is_string(...))));
    }

    public function contain(string $file): bool
    {
        foreach ($this->paths as $path) {
            if ($file === $path || str_starts_with($file, "{$path}/") || fnmatch($path, $file)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $path): string
    {
        /** @var list<string> $parts */
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            if ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
