<?php

declare(strict_types=1);

use Corpus\Response;
use Corpus\User;

test('an expectation chains through its matchers', function (): void {
    expect(1)->toBe(1)->not->toBe(2)
        ->and('a')->toBeString()->not->toBeEmpty()
        ->and([1, 2])->toHaveCount(2)->each->toBeInt();
});

test('an expectation negates with not', function (): void {
    expect(null)->not->toBeInt()->not->toBeString();
});

test('a higher-order expectation reads properties and calls methods of the value', function (): void {
    expect(new Response('ok'))->body->toBe('ok')->status->toBe(200);
    expect(new User('ada@example.com'))->domain()->toBe('example.com');
});

test('toThrow accepts a closure typed by the exception it expects', function (): void {
    expect(fn () => throw new InvalidArgumentException('no'))
        ->toThrow(function (InvalidArgumentException $exception): void {
            expect($exception->getMessage())->toBe('no');
        })
        ->toThrow(InvalidArgumentException::class, 'no');
});

test('json decodes into a new expectation', function (): void {
    expect('{"a":1}')->json()->toHaveKey('a');
});

test('a custom expectation extends every expectation', function (): void {
    expect()->extend('toBeOne', function (): Pest\Expectation {
        return $this->toBe(1);
    });

    expect(1)->toBeOne();
});
