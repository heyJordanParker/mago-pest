<?php

declare(strict_types=1);

test('a Unit test binds the test case configured for Unit', function (): void {
    expect($this->clock())->toBe(1);
});

test('a Unit test uses the trait configured for the whole suite', function (): void {
    expect($this->seed())->toBe(42);
});

test('a Unit test does not see the Feature test case', function (): void {
    // @mago-expect analysis:non-existent-method
    $this->visit('/');
});

test('a Unit test does not see properties another file assigns', function (): void {
    // @mago-expect analysis:non-documented-property
    expect($this->agent)->toBeString();
});
