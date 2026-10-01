<?php

declare(strict_types=1);

namespace MagoPest\Analyzer;

use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\FunctionLikeKind;

/**
 * What one file under Pest's test directory declares: its `pest()` and `uses()`
 * configurations, the closures Pest binds to its test case, and the matchers
 * it adds with `expect()->extend()`.
 *
 * @internal
 */
final readonly class TestFile
{
    private const array CONFIGURATION_FUNCTIONS = ['pest', 'uses'];

    private const array TEST_FUNCTIONS = ['test', 'it', 'beforeeach', 'aftereach'];

    /**
     * @param list<Configuration> $configurations
     * @param list<BoundClosure> $closures
     * @param array<string, FunctionLikeIdentifier> $matchers Matcher name => its closure.
     */
    private function __construct(
        public string $path,
        public array $configurations,
        public array $closures,
        public array $matchers,
    ) {}

    public static function read(string $path, string $contents): self
    {
        $tokens = Tokens::of($path, $contents);
        $names = new Names();
        $configurations = [];
        $closures = [];
        $matchers = [];

        foreach (array_keys($tokens->tokens) as $index) {
            $names->observe($tokens, $index);
            $function = $tokens->functionCall($index);
            if (in_array($function, self::CONFIGURATION_FUNCTIONS, true)) {
                $configurations[] = Configuration::read($tokens, $index, $names);
            }

            if (in_array($function, self::TEST_FUNCTIONS, true)) {
                array_push($closures, ...BoundClosure::inArguments($tokens, $tokens->arguments($index + 1)[0], $names));
            }

            $matchers += $function === 'expect' ? self::matcher($tokens, $index) : [];
        }

        return new self($path, $configurations, $closures, $matchers);
    }

    /**
     * Reads `expect()->extend('name', closure)` starting at the `expect` at `$index`.
     *
     * @return array<string, FunctionLikeIdentifier>
     */
    private static function matcher(Tokens $tokens, int $index): array
    {
        $extends =
            $tokens->is($index + 2, ord(')'))
            && $tokens->is($index + 3, T_OBJECT_OPERATOR)
            && $tokens->is($index + 4, 'extend')
            && $tokens->is($index + 5, ord('('));
        $arguments = $extends ? $tokens->arguments($index + 5)[0] : [];
        if (count($arguments) !== 2) {
            return [];
        }

        [$name, $closure] = $arguments;
        $isMatcher =
            $name[0] === $name[1]
            && $tokens->is($name[0], T_CONSTANT_ENCAPSED_STRING)
            && $tokens->isClosure($closure[0]);

        return (
            $isMatcher
                ? [
                    substr($tokens->tokens[$name[0]]->text, 1, -1) => new FunctionLikeIdentifier(
                        FunctionLikeKind::Closure,
                        $tokens->closureName($closure[0]),
                    ),
                ]
                : []
        );
    }
}
