<?php

declare(strict_types=1);

use Corpus\User;

test('a matcher called with too few arguments is reported', function (): void {
    // @mago-expect analysis:too-few-arguments
    expect(1)->toBe();
});

test('a second argument to and() is reported, since and() takes one', function (): void {
    // @mago-expect analysis:too-many-arguments
    expect(1)->toBe(1)->and(2, 'ignored message');
});

test('a matcher argument of the wrong type is reported', function (): void {
    // @mago-expect analysis:invalid-argument
    expect([1])->toHaveCount('one');
});

test('a custom matcher keeps its own parameters', function (): void {
    expect()->extend('toBeNamed', function (string $name): Pest\Expectation {
        return $this->toBe($name);
    });

    // @mago-expect analysis:invalid-argument
    expect('ada')->toBeNamed(new User('ada@example.com'));
});

describe('a describe body', function (): void {
    $helper = function (): void {
        // @mago-expect analysis:undefined-variable
        // @mago-expect analysis:mixed-method-access
        $this->name();
    };

    test('runs unbound, as Pest runs it', function () use ($helper): void {
        expect($helper)->toBeCallable();
    });
});
