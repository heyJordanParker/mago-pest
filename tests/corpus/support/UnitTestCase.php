<?php

declare(strict_types=1);

namespace Corpus;

use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    public function clock(): int
    {
        return 1;
    }
}
