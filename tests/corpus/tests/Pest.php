<?php

declare(strict_types=1);

use Corpus\FeatureTestCase;
use Corpus\Seeds;
use Corpus\SignsIn;
use Corpus\UnitTestCase;

pest()
    ->extends(FeatureTestCase::class)
    ->use(SignsIn::class)
    ->beforeEach(function (): void {
        $this->home = $this->visit('/');
    })
    ->afterEach(function (): void {
        expect($this->home->status)->toBe(200);
    })
    ->in('Feature');

uses(UnitTestCase::class)->in('Unit');

uses(Seeds::class)->in(__DIR__);
