<?php

declare(strict_types=1);

namespace App\Domain\Recipes;

use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

final class RecipeContentTranslator
{
    /**
     * @var array<string, string> translated templates indexed by their English source
     */
    private array $messages = [];

    /**
     * Load explicit content translations without duplicating recipe measurements.
     */
    public function __construct(
        /**
         * Selected language for all authored recipe content.
         */
        private readonly string $locale = 'en',
    )
    {
        if (!\in_array($locale, ['en', 'cs', 'sk'], true)) {
            throw new RuntimeException('Unsupported recipe locale: ' . $locale);
        }
        if ($locale === 'en') {
            return;
        }
        $source = $this->dictionary('en');
        $translated = $this->dictionary($locale);
        if (\array_keys($source) !== \array_keys($translated)) {
            throw new RuntimeException('Recipe translation keys must match for ' . $locale);
        }
        foreach ($source as $key => $text) {
            \preg_match_all('/\\{n\\d+\\}/', $text, $sourceNumbers);
            \preg_match_all('/\\{n\\d+\\}/', $translated[$key], $translatedNumbers);
            \sort($sourceNumbers[0]);
            \sort($translatedNumbers[0]);
            if ($sourceNumbers[0] !== $translatedNumbers[0] || $translated[$key] === '') {
                throw new RuntimeException('Recipe translation must preserve measurement placeholders: ' . $key);
            }
            $this->messages[$text] = $translated[$key];
        }
    }

    /**
     * Translate authored text, retaining every number from the selected recipe.
     */
    public function text(string $text): string
    {
        if ($text === '' || $this->locale === 'en') {
            return $text;
        }
        $numbers = [];
        $template = \preg_replace_callback('/\\d+(?:\\.\\d+)?/', function (array $match) use (&$numbers): string {
            $key = '{n' . (\count($numbers) + 1) . '}';
            $numbers[$key] = \str_replace('.', ',', $match[0]);

            return $key;
        }, $text) ?? throw new RuntimeException('Unable to tokenize recipe measurements.');

        return \strtr($this->messages[$template] ?? throw new RuntimeException('Missing ' . $this->locale . ' recipe translation: ' . $template), $numbers);
    }

    /**
     * @return array<string, string> read one fixed, authored content dictionary
     */
    private function dictionary(string $locale): array
    {
        $messages = [];
        foreach (Typer::assertArray(\json_decode(Typer::assertString(\file_get_contents(\resource_path('recipes/locales/' . $locale . '.json'))), true, flags: \JSON_THROW_ON_ERROR)) as $key => $value) {
            $messages[Typer::assertString($key)] = Typer::assertString($value);
        }

        return $messages;
    }
}
