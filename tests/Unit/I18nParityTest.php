<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/*
 * I18n key-parity test.
 *
 * Frontend i18n: `resources/js/i18n/{en,cs,sk}.json`
 * Backend i18n: `lang/{en,cs,sk}.json`
 *
 * All three locales within each set must share an identical key
 * tree. Without this test, adding a key to `en.json` without
 * mirroring it to `cs.json`/`sk.json` (or vice versa) would not be
 * caught at the CI layer.
 */
\test('frontend i18n keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('resources/js/i18n');
    $en = \json_decode((string) \file_get_contents($base . '/en.json'), true, flags: \JSON_THROW_ON_ERROR);
    $cs = \json_decode((string) \file_get_contents($base . '/cs.json'), true, flags: \JSON_THROW_ON_ERROR);
    $sk = \json_decode((string) \file_get_contents($base . '/sk.json'), true, flags: \JSON_THROW_ON_ERROR);

    $enKeys = flatten_keys($en);
    $csKeys = flatten_keys($cs);
    $skKeys = flatten_keys($sk);

    \expect($csKeys)->toEqual($enKeys, 'cs.json is missing keys present in en.json or has extras.');
    \expect($skKeys)->toEqual($enKeys, 'sk.json is missing keys present in en.json or has extras.');
});

\test('backend i18n keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('lang');
    $en = \json_decode((string) \file_get_contents($base . '/en.json'), true, flags: \JSON_THROW_ON_ERROR);
    $cs = \json_decode((string) \file_get_contents($base . '/cs.json'), true, flags: \JSON_THROW_ON_ERROR);
    $sk = \json_decode((string) \file_get_contents($base . '/sk.json'), true, flags: \JSON_THROW_ON_ERROR);

    $enKeys = flatten_keys($en);
    $csKeys = flatten_keys($cs);
    $skKeys = flatten_keys($sk);

    \expect($csKeys)->toEqual($enKeys, 'lang/cs.json is missing keys present in lang/en.json or has extras.');
    \expect($skKeys)->toEqual($enKeys, 'lang/sk.json is missing keys present in lang/en.json or has extras.');
});

\test('core notification keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('packages/thinkycz/laravel-core/lang');
    $en = require $base . '/en/notifications.php';
    $cs = require $base . '/cs/notifications.php';
    $sk = require $base . '/sk/notifications.php';
    $enKeys = flatten_keys($en);

    \expect(flatten_keys($cs))->toEqual($enKeys, 'Core Czech notifications are missing English keys or have extras.')
        ->and(flatten_keys($sk))->toEqual($enKeys, 'Core Slovak notifications are missing English keys or have extras.');
});

\test('core HTTP status keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('packages/thinkycz/laravel-core/lang');
    $en = require $base . '/en/statuses.php';
    $cs = require $base . '/cs/statuses.php';
    $sk = require $base . '/sk/statuses.php';
    $enKeys = flatten_keys($en);

    \expect(flatten_keys($cs))->toEqual($enKeys, 'Core Czech statuses are missing English keys or have extras.')
        ->and(flatten_keys($sk))->toEqual($enKeys, 'Core Slovak statuses are missing English keys or have extras.')
        ->and($sk[419])->toBe('Vaša relácia vypršala. Obnovte stránku a skúste to znova.');
});

\test('password broker keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('lang');
    $en = require $base . '/en/passwords.php';
    $cs = require $base . '/cs/passwords.php';
    $sk = require $base . '/sk/passwords.php';
    $enKeys = flatten_keys($en);

    \expect(flatten_keys($cs))->toEqual($enKeys, 'Czech password messages are missing English keys or have extras.')
        ->and(flatten_keys($sk))->toEqual($enKeys, 'Slovak password messages are missing English keys or have extras.');
});

\test('every static backend translation resolves in every supported locale', function (): void {
    $locales = ['en', 'cs', 'sk'];
    $jsonTranslations = [];

    foreach ($locales as $locale) {
        $jsonTranslations[$locale] = \json_decode(
            (string) \file_get_contents(\base_path("lang/{$locale}.json")),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }

    $missing = [];

    foreach (backend_php_files() as $path) {
        foreach (static_translation_keys($path) as $key) {
            foreach ($locales as $locale) {
                if (translation_key_resolves($key, $locale, $jsonTranslations[$locale])) {
                    continue;
                }

                $missing[] = $locale . ': ' . $key . ' (' . \str_replace(\base_path() . '/', '', $path) . ')';
            }
        }
    }

    \sort($missing);

    \expect($missing)->toEqual([], "Static backend translations are missing:\n" . \implode("\n", $missing));
});

/**
 * Flatten a nested array into a sorted list of dot-paths so equality
 * checks ignore the order in which keys appear.
 *
 * @param array<mixed> $data
 *
 * @return array<int, string>
 */
function flatten_keys(array $data, string $prefix = ''): array
{
    $keys = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (\is_array($value)) {
            $keys = \array_merge($keys, flatten_keys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    \sort($keys);

    return $keys;
}

/**
 * Return the application PHP files whose user-facing strings belong to this
 * application's translation dictionaries.
 *
 * @return list<string>
 */
function backend_php_files(): array
{
    $files = [];

    foreach ([\base_path('app'), \base_path('routes')] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }
    }

    \sort($files);

    return $files;
}

/**
 * Extract only literal calls such as __('Message') or \__('auth.failed').
 * Dynamic keys are intentionally left to their owning runtime tests.
 *
 * @return list<string>
 */
function static_translation_keys(string $path): array
{
    $tokens = \token_get_all((string) \file_get_contents($path));
    $keys = [];

    foreach ($tokens as $index => $token) {
        if (
            !\is_array($token) ||
            !\in_array($token[0], [\T_STRING, \T_NAME_FULLY_QUALIFIED], true) ||
            \ltrim($token[1], '\\') !== '__'
        ) {
            continue;
        }

        $openParenthesis = next_significant_token($tokens, $index + 1);

        if ($openParenthesis === null || $openParenthesis['token'] !== '(') {
            continue;
        }

        $literal = next_significant_token($tokens, $openParenthesis['index'] + 1);

        if ($literal === null || !\is_array($literal['token']) || $literal['token'][0] !== \T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $keys[] = decode_php_string_literal($literal['token'][1]);
    }

    return \array_values(\array_unique($keys));
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 *
 * @return array{index: int, token: array{0: int, 1: string, 2: int}|string}|null
 */
function next_significant_token(array $tokens, int $offset): array|null
{
    for ($index = $offset, $count = \count($tokens); $index < $count; ++$index) {
        $token = $tokens[$index];

        if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
            continue;
        }

        return ['index' => $index, 'token' => $token];
    }

    return null;
}

function decode_php_string_literal(string $literal): string
{
    $value = \mb_substr($literal, 1, -1);

    if ($literal[0] === '\'') {
        return \str_replace(['\\\\', '\\\''], ['\\', '\''], $value);
    }

    return \stripcslashes($value);
}

/**
 * @param array<string, mixed> $jsonTranslations
 */
function translation_key_resolves(string $key, string $locale, array $jsonTranslations): bool
{
    if (\array_key_exists($key, $jsonTranslations)) {
        return true;
    }

    if (!\str_contains($key, '.')) {
        return false;
    }

    [$namespace, $nestedKey] = \explode('.', $key, 2);
    $path = \base_path("lang/{$locale}/{$namespace}.php");

    if (!\is_file($path)) {
        return false;
    }

    $translations = require $path;

    return \is_array($translations) && Arr::has($translations, $nestedKey);
}
