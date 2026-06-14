<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Typer;

\test('Typer::assertString returns the string when given a string', function (): void {
    \expect(Typer::assertString('hello'))->toBe('hello');
});

\test('Typer::assertInt returns the int when given an int', function (): void {
    \expect(Typer::assertInt(42))->toBe(42);
});

\test('Typer::assertNullableString returns null for null and the value for a string', function (): void {
    \expect(Typer::assertNullableString(null))->toBeNull();
    \expect(Typer::assertNullableString('hello'))->toBe('hello');
});

\test('Typer::parseBool accepts the usual truthy spellings', function (): void {
    \expect(Typer::parseBool(true))->toBeTrue();
    \expect(Typer::parseBool('1'))->toBeTrue();
    \expect(Typer::parseBool('on'))->toBeTrue();
    \expect(Typer::parseBool('yes'))->toBeTrue();
    \expect(Typer::parseBool('true'))->toBeTrue();
    \expect(Typer::parseBool(false))->toBeFalse();
    \expect(Typer::parseBool('0'))->toBeFalse();
    \expect(Typer::parseBool('off'))->toBeFalse();
    \expect(Typer::parseBool('no'))->toBeFalse();
    \expect(Typer::parseBool(null))->toBeFalse();
});
