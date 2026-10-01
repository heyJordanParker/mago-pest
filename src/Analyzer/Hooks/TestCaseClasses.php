<?php

declare(strict_types=1);

namespace MagoPest\Analyzer\Hooks;

use Mago\Sdk\Analyzer\InitializationContext;
use Mago\Sdk\Analyzer\InitializationHook;
use MagoPest\Analyzer\Configuration;
use MagoPest\Analyzer\TestFile;
use MagoPest\Analyzer\TestSuite;
use Override;
use Pest\Concerns\Expectable;
use Pest\Concerns\Testable;
use Pest\Contracts\HasPrintableTestCaseName;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Declares the class Pest evaluates for every test file, and one class per
 * `pest()` or `uses()` declaration that runs hooks, so each closure Pest binds
 * has the `$this` it has when the test runs.
 *
 * Pest's class accepts any property through `#[AllowDynamicProperties]`, which
 * Mago does not model. Mago models dynamic properties as magic ones, so the
 * class declares `__get` and `__set`, and `TestCaseProperties` types each
 * property by what the closures assign.
 *
 * @internal
 */
final readonly class TestCaseClasses implements InitializationHook
{
    public function __construct(
        private TestSuite $suite,
    ) {}

    #[Override]
    public function initialize(InitializationContext $context): void
    {
        $files = array_map(static fn(string $path): TestFile => TestFile::read(
            $path,
            (string) file_get_contents($path),
        ), $this->paths());
        $configurations = array_merge(...array_map(static fn(TestFile $file): array => $file->configurations, $files));
        $hooked = array_values(array_filter(
            $configurations,
            static fn(Configuration $configuration): bool => $configuration->hooks !== [],
        ));

        foreach ($hooked as $configuration) {
            $class = self::hookClass($configuration);
            $context->addStub("{$class}.php", self::stub($class, $configuration->classes));
            $this->suite->declare($class, $configuration->hooks, []);
        }

        foreach ($files as $file) {
            $this->suite->addMatchers($file->matchers);
            if ($file->closures === []) {
                continue;
            }

            $applies = static fn(Configuration $configuration): bool => $configuration->targets->contain($file->path);
            $class = self::className($file->path);
            $classes = array_values(array_merge(...array_map(
                static fn(Configuration $configuration): array => $configuration->classes,
                array_filter($configurations, $applies),
            )));
            $context->addStub("{$class}.php", self::stub($class, $classes));
            $this->suite->declare(
                $class,
                $file->closures,
                array_values(array_map(self::hookClass(...), array_filter($hooked, $applies))),
            );
        }
    }

    /**
     * Mirrors `Pest\Factories\TestCaseFactory::evaluate()`: `P\` followed by the
     * file's path, its first letter upper-cased, reduced to name characters.
     */
    private static function className(string $path): string
    {
        $basename = explode('.', basename($path))[0];
        $relative = dirname(ucfirst($path)) . '/' . $basename;

        return 'P\\' . (string) preg_replace('/[^A-Za-z0-9\\\\]/', '', str_replace('/', '\\', $relative));
    }

    private static function hookClass(Configuration $configuration): string
    {
        return self::className($configuration->path) . 'Hooks' . $configuration->offset;
    }

    /**
     * The class `Pest\Factories\TestCaseFactory::evaluate()` evaluates, with
     * magic accessors in place of `#[AllowDynamicProperties]`.
     *
     * @param list<string> $classesAndTraits
     */
    private static function stub(string $class, array $classesAndTraits): string
    {
        $unique = array_values(array_unique($classesAndTraits));
        $traits = [Testable::class, Expectable::class, ...array_filter($unique, trait_exists(...))];
        $parents = array_values(array_filter($unique, static fn(string $name): bool => !trait_exists($name)));
        $parent = $parents === [] ? TestCase::class : $parents[count($parents) - 1];
        $namespace = substr($class, 0, (int) strrpos($class, '\\'));
        $name = substr($class, strlen($namespace) + 1);
        $uses = implode(', ', array_map(static fn(string $trait): string => "\\{$trait}", $traits));
        $printable = HasPrintableTestCaseName::class;

        return <<<PHP
            <?php

            namespace {$namespace};

            final class {$name} extends \\{$parent} implements \\{$printable}
            {
                use {$uses};

                public function __get(string \$name): mixed {}

                public function __set(string \$name, mixed \$value): void {}
            }
            PHP;
    }

    /**
     * @return list<string> Workspace-relative paths of the PHP files under the test directory, in path order.
     */
    private function paths(): array
    {
        if (!is_dir($this->suite->directory)) {
            throw new RuntimeException(
                "Pest's test directory `{$this->suite->directory}` does not exist in the worker's working directory `"
                . (string) getcwd()
                . '`. Run the worker from the Mago workspace.',
            );
        }

        $paths = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->suite->directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = str_replace('\\', '/', $file->getPathname());
        }

        sort($paths, SORT_STRING);

        return $paths;
    }
}
