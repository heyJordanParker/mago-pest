<?php

declare(strict_types=1);

use Mago\Sdk\Worker;
use MagoPest\PestExtension;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Worker(PestExtension::create(testDirectory: 'tests/corpus/tests')))->run();
