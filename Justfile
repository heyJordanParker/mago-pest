set dotenv-load := false

mago := "vendor/bin/mago"

validate:
    composer validate --strict --no-check-publish

lint:
    {{mago}} --config mago.toml lint

analyze:
    {{mago}} --config mago.toml analyze

format:
    {{mago}} --config mago.toml format

format-check:
    {{mago}} --config mago.toml format --check

test-corpus:
    {{mago}} --workspace . --config tests/corpus/mago.toml analyze --reporting-format count

test-unit:
    vendor/bin/pest tests/Unit

check: validate format-check lint analyze test-corpus test-unit
