<?php

declare(strict_types=1);

namespace Corpus;

final class Response
{
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
    ) {}
}
