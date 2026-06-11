<?php

declare(strict_types=1);

/**
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

    $enKeys = \flatten_keys($en);
    $csKeys = \flatten_keys($cs);
    $skKeys = \flatten_keys($sk);

    \expect($csKeys)->toEqual($enKeys, 'cs.json is missing keys present in en.json or has extras.');
    \expect($skKeys)->toEqual($enKeys, 'sk.json is missing keys present in en.json or has extras.');
});

\test('backend i18n keys are identical across en, cs, and sk', function (): void {
    $base = \base_path('lang');
    $en = \json_decode((string) \file_get_contents($base . '/en.json'), true, flags: \JSON_THROW_ON_ERROR);
    $cs = \json_decode((string) \file_get_contents($base . '/cs.json'), true, flags: \JSON_THROW_ON_ERROR);
    $sk = \json_decode((string) \file_get_contents($base . '/sk.json'), true, flags: \JSON_THROW_ON_ERROR);

    $enKeys = \flatten_keys($en);
    $csKeys = \flatten_keys($cs);
    $skKeys = \flatten_keys($sk);

    \expect($csKeys)->toEqual($enKeys, 'lang/cs.json is missing keys present in lang/en.json or has extras.');
    \expect($skKeys)->toEqual($enKeys, 'lang/sk.json is missing keys present in lang/en.json or has extras.');
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
            $keys = \array_merge($keys, \flatten_keys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    \sort($keys);

    return $keys;
}
