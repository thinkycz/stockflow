<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeEnumCommand extends GeneratorCommand
{
    /**
     * @inheritDoc
     */
    protected $name = 'make:enum-custom';

    /**
     * @inheritDoc
     */
    protected $description = 'Create a new custom Enum class';

    /**
     * @inheritDoc
     */
    protected $type = 'Enum';

    /**
     * @inheritDoc
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('stubs/enum.stub');
    }

    /**
     * Resolve the fully qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = $this->laravel->basePath($stub);

        return \file_exists($customPath) ? $customPath : __DIR__ . '/../../' . $stub;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(mixed $rootNamespace): string
    {
        return parent::getDefaultNamespace($rootNamespace) . '\\Enums';
    }
}
