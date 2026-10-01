<?php

declare(strict_types=1);

namespace MagoPest;

use Composer\InstalledVersions;
use InvalidArgumentException;
use Mago\Sdk\Extension;
use MagoPest\Analyzer\PestPlugin;

/**
 * Constructs the Pest extension advertised by each worker process.
 *
 * @api
 */
final class PestExtension
{
    private function __construct() {}

    /**
     * @param non-empty-string $testDirectory Pest's test directory, relative to the Mago workspace.
     */
    public static function create(string $testDirectory = 'tests'): Extension
    {
        $directory = rtrim($testDirectory, '/');
        if ($directory === '') {
            throw new InvalidArgumentException(
                "Pest's test directory must name a folder in the Mago workspace, and `{$testDirectory}` names none.",
            );
        }

        return new Extension(
            identifier: 'heyjordanparker/mago-pest',
            name: 'Pest',
            version: InstalledVersions::getPrettyVersion('heyjordanparker/mago-pest') ?? 'dev',
            analyzerPlugins: [new PestPlugin($directory)],
        );
    }
}
