<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Traits;

use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Typer;

trait ParserTrait
{
    /**
     * Parser getter.
     */
    public function parser(string|null $key = null): Parser
    {
        $value = $this->mixed($key);

        if (\is_array($value)) {
            return new Parser($value);
        }

        return new Parser(['scalar' => $value]);
    }

    /**
     * Parsers getter.
     *
     * @return array<int, Parser>
     */
    public function parsers(string|null $key = null): array
    {
        $parsers = [];

        foreach (Typer::assertNullableArray($this->mixed($key)) ?? [] as $value) {
            if (\is_array($value)) {
                $parsers[] = new Parser($value);
            } else {
                $parsers[] = new Parser(['scalar' => $value]);
            }
        }

        return $parsers;
    }
}
