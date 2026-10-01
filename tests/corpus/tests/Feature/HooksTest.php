<?php

declare(strict_types=1);

use Corpus\Response;
use Corpus\User;

beforeEach(function (): void {
    $this->agent = 'Mozilla/5.0';
    $this->attempts = 3;
    $this->user = new User('ada@example.com');
    $this->pageIds = [];
    $this->ids = [];
    $this->verifier = str_repeat('a', 43);
    $this->ratio = (float) '0.5';
    $this->fetch = function (#[SensitiveParameter] string $path): Response {
        return $this->visit($path);
    };
    $this->domainOf = fn (User $user): string => $user->domain();
    $this->filter = "status IN ({$this->attempts})";
    $this->after = new User('after@example.com');
});

afterEach(function (): void {
    expect($this->pageIds)->toBeArray();
});

test('a test closure reads the test case and the trait it uses', function (): void {
    $response = $this->visit('/');
    $user = $this->signIn('grace@example.com');

    expect($response->body)->toBe('/')
        ->and($user->domain())->toBe('example.com')
        ->and($this->seed())->toBe(42);
});

it('reads the properties every beforeEach in the file assigns', function (): void {
    $this->pageIds[] = $this->attempts;

    expect($this->agent)->toBeString()
        ->and($this->user->domain())->toBe('example.com')
        ->and($this->ratio)->toBeFloat()
        ->and(($this->fetch)('/pricing')->status)->toBe(200)
        ->and(($this->domainOf)($this->user))->toBe('example.com')
        ->and($this->filter)->toBeString()
        ->and($this->after->domain())->toBe('example.com');
});

it('reads a property assigned a call-dependent return type as mixed', function (): void {
    // @mago-expect analysis:mixed-argument
    expect(hash('sha256', $this->verifier))->toBeString();
});

it('reads a property assigned an untyped value anywhere as mixed', function (): void {
    // @mago-expect analysis:mixed-argument
    $this->ids = array_reverse($this->ids);

    // @mago-expect analysis:mixed-assignment
    foreach ($this->ids as $id) {
        expect($id)->not->toBeNull();
    }
});

it('reads the properties the global beforeEach in Pest.php assigns', function (): void {
    expect($this->home->body)->toBe('/');
});

it('still reports a property nothing assigns', function (): void {
    // @mago-expect analysis:non-documented-property
    expect($this->missing)->toBeNull();
});

it('still reports a closure called with the wrong argument', function (): void {
    // @mago-expect analysis:invalid-argument
    ($this->fetch)(42);
});

describe('a describe block', function (): void {
    beforeEach(function (): void {
        $this->nested = new User('lin@example.com');
    });

    it('binds closures inside describe to the test case', function (): void {
        expect($this->nested->domain())->toBe('example.com')
            ->and($this->agent)->toBeString();
    });
});
