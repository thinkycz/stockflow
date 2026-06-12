<?php

declare(strict_types=1);

if (\is_dir(\dirname(__DIR__, 2) . '/app/Http/Validation')) {
    \arch('validity classes end with the Validity suffix', function (): void {
        \expect('App\\Http\\Validation')
            ->toHaveSuffix('Validity');
    });

    \arch('validity classes expose a BaseValidity property', function (): void {
        foreach (\glob(\base_path('app/Http/Validation/*.php')) as $file) {
            $contents = (string) \file_get_contents($file);

            \expect($contents)
                ->toMatch('/public BaseValidity \\$baseValidity/');
        }
    });

    \arch('validity classes construct BaseValidity', function (): void {
        foreach (\glob(\base_path('app/Http/Validation/*.php')) as $file) {
            $contents = (string) \file_get_contents($file);

            \expect($contents)
                ->toMatch('/\\$this->baseValidity = new BaseValidity\\(\\)/');
        }
    });

    \arch('validity id() methods call ->exists on their own table', function (): void {
        foreach (\glob(\base_path('app/Http/Validation/*.php')) as $file) {
            $contents = (string) \file_get_contents($file);

            if (\preg_match('/public function id\\(\\): Validity\\s*\\{[^}]*exists\\(\'([^\']+)\',\\s*\'id\'(?:,\\s*[^)]+)?\\)/', $contents, $matches) === 1) {
                $table = $matches[1];

                \expect($contents)
                    ->toMatch('/public function id\\(\\): Validity\\s*\\{[^}]*exists\\(\'' . \preg_quote($table, '/') . '\',\\s*\'id\'(?:,\\s*[^)]+)?\\)/');
            }
        }
    });

    \arch('validity classes never call ->required() or ->nullable() directly', function (): void {
        foreach (\glob(\base_path('app/Http/Validation/*.php')) as $file) {
            $contents = (string) \file_get_contents($file);

            \expect($contents)
                ->not->toMatch('/->required\\(\\)/')
                ->not->toMatch('/->nullable\\(\\)/');
        }
    });
}
