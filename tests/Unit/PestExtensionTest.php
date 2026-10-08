<?php

declare(strict_types=1);

test('the extension reports the dev version when Composer did not install mago-pest', function (): void {
    $worker = sprintf(<<<'PHP'
        $loader = require %s;
        $loader->unregister();
        spl_autoload_register($loader->loadClass(...));
        Composer\InstalledVersions::reload(['root' => ['name' => 'acme/app'], 'versions' => []]);
        echo MagoPest\PestExtension::create()->version;
        PHP, var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true));

    exec(PHP_BINARY . ' -r ' . escapeshellarg($worker) . ' 2>&1', $output);

    expect($output)->toBe(['dev']);
});
