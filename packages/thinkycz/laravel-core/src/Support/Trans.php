<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Translation\Translator;
use Thinkycz\LaravelCore\Traits\InjectTrait;

class Trans
{
    use InjectTrait;

    /**
     * Translator.
     */
    public Translator $translator;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translator = Resolver::resolveTranslator();
    }

    /**
     * Must translate to string.
     *
     * @param array<string, string> $replace
     */
    public function assertString(string $key, array $replace = [], string|null $locale = null, bool $fallback = true): string
    {
        \assert($this->translator->has($key, $locale, $fallback), Panicker::message(__METHOD__, 'translation must exists', \compact('key', 'locale', 'fallback')));

        $value = $this->translator->get($key, $replace, $locale, $fallback);

        \assert(\is_string($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value', 'locale', 'fallback')));

        return $value;
    }

    /**
     * Must translate to array.
     *
     * @param array<string, string> $replace
     *
     * @return array<array-key, string>
     */
    public function assertArray(string $key, array $replace = [], string|null $locale = null, bool $fallback = true): array
    {
        \assert($this->translator->has($key, $locale, $fallback), Panicker::message(__METHOD__, 'translation must exists', \compact('key', 'locale', 'fallback')));

        $value = $this->translator->get($key, $replace, $locale, $fallback);

        \assert(\is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value', 'locale', 'fallback')));

        return Typer::assertStringArray($value);
    }
}
