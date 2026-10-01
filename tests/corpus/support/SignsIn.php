<?php

declare(strict_types=1);

namespace Corpus;

trait SignsIn
{
    public function signIn(string $email): User
    {
        return new User($email);
    }
}
