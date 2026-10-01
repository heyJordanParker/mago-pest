<?php

declare(strict_types=1);

namespace Corpus;

use PHPUnit\Framework\TestCase;

abstract class FeatureTestCase extends TestCase
{
    public function visit(string $path): Response
    {
        return new Response($path);
    }
}
