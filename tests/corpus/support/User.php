<?php

declare(strict_types=1);

namespace Corpus;

final class User
{
    public function __construct(
        public readonly string $email,
    ) {}

    public function domain(): string
    {
        return substr($this->email, (int) strpos($this->email, '@') + 1);
    }
}
