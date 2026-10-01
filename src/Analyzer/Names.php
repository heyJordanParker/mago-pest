<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use PhpToken;

/**
 * Resolves class names the way PHP does, from the namespace and the class
 * imports seen so far in one file.
 *
 * @internal
 */
final class Names
{
    private const array NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private string $namespace = '';

    /** @var array<string, string> Lower-cased alias => fully qualified name. */
    private array $imports = [];

    /**
     * Records the namespace or the class imports declared at `$index`.
     */
    public function observe(Tokens $tokens, int $index): void
    {
        if ($tokens->is($index, T_NAMESPACE) && $tokens->is($index + 1, [T_STRING, T_NAME_QUALIFIED])) {
            $this->namespace = $tokens->tokens[$index + 1]->text;
            $this->imports = [];
        }

        $importsClasses =
            $tokens->is($index, T_USE)
            && !$tokens->is($index - 1, ord(')'))
            && !$tokens->is($index + 1, [T_FUNCTION, T_CONST]);
        if ($importsClasses) {
            $this->import($tokens, $index + 1);
        }
    }

    public function resolve(PhpToken $name): string
    {
        $parts = explode('\\', $name->text, 2);
        $base = $this->imports[strtolower($parts[0])] ?? ltrim("{$this->namespace}\\{$parts[0]}", '\\');

        return (
            $name->is(T_NAME_FULLY_QUALIFIED)
                ? ltrim($name->text, '\\')
                : implode('\\', [$base, ...array_slice($parts, 1)])
        );
    }

    /**
     * Reads the `use` statement whose first name is at `$index`, up to its `;`.
     */
    private function import(Tokens $tokens, int $index): void
    {
        $name = '';
        foreach (array_slice($tokens->tokens, $index, preserve_keys: true) as $position => $token) {
            if ($token->id === ord(';')) {
                return;
            }

            if (!$token->is(self::NAMES)) {
                continue;
            }

            $isAlias = $tokens->is($position - 1, T_AS);
            $name = $isAlias ? $name : ltrim($token->text, '\\');
            $this->imports[strtolower($isAlias ? $token->text : self::shortName($name))] = $name;
        }
    }

    private static function shortName(string $name): string
    {
        return substr($name, (int) strrpos('\\' . $name, '\\'));
    }
}
