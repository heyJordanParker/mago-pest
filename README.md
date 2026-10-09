# Mago Pest

A [mago-sharp](https://github.com/heyJordanParker/mago-sharp) analyzer extension that types [Pest](https://pestphp.com) test files the way Pest runs them.

Without it, Mago reads a Pest test file as plain PHP. It cannot see the test case class that `$this` refers to inside a test closure, so every call to a test case method or helper trait, and every property a `beforeEach` hook sets, is reported. Expectation chains are reported too, because Pest dispatches most of them through `__call` and `__get`.

With it, Mago analyzes:

- `$this` in `test()`, `it()`, `beforeEach()`, and `afterEach()` closures, and in the `beforeEach()` and `afterEach()` hooks of `pest()` and `uses()`, as the test case Pest builds from `pest()->extend()`, `pest()->use()`, `uses()`, and their `in()` targets.
- Properties that those closures assign to `$this`, typed by the assigned values.
- Matcher chains: `expect($value)->toBe(1)->not->toBeNull()->each->toBeInt()`.
- Higher-order expectations: `expect($user)->name->toBe('Ada')` and `expect($user)->domain()->toBe('example.com')`.
- Custom matchers from `expect()->extend('name', closure)`, with the closure's parameters.
- `toThrow()` with a closure typed by the exception it expects.

## Install

```shell
composer require --dev heyjordanparker/mago-sharp:^0.2 heyjordanparker/mago-pest
```

Keep the `^0.2` constraint. Packagist also lists the old 1.x versions of `heyjordanparker/mago-sharp`, and mago-pest runs only on 0.2.

The project owns its worker entrypoint. Create `.mago/extensions.php`:

```php
<?php

declare(strict_types=1);

use Mago\Sdk\Worker;
use MagoPest\PestExtension;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(PestExtension::create()))->run();
```

Register it in `mago.toml`:

```toml
[extension-hosts.pest]
command = ["php", ".mago/extensions.php"]
```

`PestExtension::create()` takes one option, `testDirectory`. It is Pest's test directory relative to the Mago workspace, and it defaults to `tests`:

```php
(new Worker(PestExtension::create(testDirectory: 'src/Tests')))->run();
```

A worker can host several extensions:

```php
(new Worker(App\Mago\Extension::create(), PestExtension::create()))->run();
```

## How it works

Before Mago parses the project, the extension reads every PHP file under the test directory. For each test file it declares the class Pest generates, `P\<path>`, with the same parent class and traits Pest gives it. Each `pest()` or `uses()` declaration with hooks gets a class of its own, and test files share the properties those hooks assign.

Pest marks its generated class `#[AllowDynamicProperties]`. Mago does not model that attribute, so the declared class has `__get` and `__set` instead. Mago therefore reports a property that no closure assigns as `non-documented-property`, not `non-existent-property`.

A property's type is the union of every value assigned to it. The extension reads each value from its tokens, so it can type only a value whose tokens state the type:

- a literal, a string, an array, or a cast
- `new` of a named class
- a closure, typed by its own signature
- one call to a function, a static method, or a method on `$this`, typed by its declared return type

Any other value is `mixed`. That includes a variable, an array access, a chained call, an arithmetic expression, and a return type that depends on templates. A property assigned any `mixed` value is `mixed`.

## Limits

- Closures in a `describe()` body outside a test or hook do not run on a test case, and Pest does not bind `$this` in them. Mago reports `$this` there, as it should.
- `Closure::bind()` and `Closure::call()` rebind `$this` at runtime, and the extension does not follow them.
- A `pest()` or `uses()` declaration is read only when its classes are given as `Name::class` or as a string, and its `in()` targets as strings or `__DIR__`.

## Development

```shell
composer install
just check
```

`just check` validates `composer.json`, then checks formatting, lints, analyzes the source, runs the corpus, and runs the unit tests in `tests/Unit`. The corpus in `tests/corpus` runs the real worker over Pest test files and checks every inline `@mago-expect` annotation. A test in it that passes shows a capability, and an annotated line shows a defect the extension still reports.

## License

MIT
