<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Input\InputOption;
use Thinkycz\LaravelCore\Support\Typer;

class MakeCrudShowCommand extends GeneratorCommand
{
    /**
     * @inheritDoc
     */
    protected $name = 'make:crud-show';

    /**
     * @inheritDoc
     */
    protected $description = 'Create CRUD show controller for a given model';

    /**
     * @inheritDoc
     */
    protected $type = 'Controller';

    /**
     * @inheritDoc
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('stubs/controller.show.stub');
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
    protected function buildClass(mixed $name): string
    {
        $namespaceModel = $this->option('model') !== null
            ? $this->qualifyModel(Typer::parseString($this->option('model')))
            : $this->qualifyModel(Typer::parseString($this->guessModelName($name)));

        $model = \class_basename($namespaceModel);

        $replace = [
            '{{ namespacedModel }}' => $namespaceModel,
            '{{ model }}' => $model,
        ];

        return \str_replace(
            \array_keys($replace),
            \array_values($replace),
            parent::buildClass($name),
        );
    }

    /**
     * @inheritDoc
     */
    protected function replaceNamespace(mixed &$stub, mixed $name): static
    {
        $namespaceModel = $this->option('model') !== null
            ? $this->qualifyModel(Typer::parseString($this->option('model')))
            : $this->qualifyModel(Typer::parseString($this->guessModelName($name)));

        $model = \class_basename($namespaceModel);

        $replace = [
            '{{ namespace }}' => 'App\\Http\\Controllers\\Api\\' . $model,
        ];

        $stub = \str_replace(
            \array_keys($replace),
            \array_values($replace),
            $stub,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function replaceClass(mixed $stub, mixed $name): string
    {
        $name = (new Stringable($name))->replaceFirst($this->rootNamespace(), '')->finish('ShowController')->value();

        $class = \str_replace($this->getNamespace($name) . '\\', '', $name);

        return \str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class, $stub);
    }

    /**
     * @inheritDoc
     */
    protected function getPath(mixed $name): string
    {
        $name = (new Stringable($name))->replaceFirst($this->rootNamespace(), '')->finish('ShowController')->value();

        $namespaceModel = $this->option('model') !== null
            ? $this->qualifyModel(Typer::parseString($this->option('model')))
            : $this->qualifyModel(Typer::parseString($this->guessModelName($name)));

        $model = \class_basename($namespaceModel);

        return $this->laravel->basePath() . '/app/Http/Controllers/Api/' . $model . '/' . \str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Guess model name.
     */
    protected function guessModelName(string $name): string
    {
        if (Str::endsWith($name, 'ShowController')) {
            $name = Str::before($name, 'ShowController');
        }

        $modelName = $this->qualifyModel(Str::after($name, $this->rootNamespace()));

        if (\class_exists($modelName)) {
            return $modelName;
        }

        if (\is_dir(\app_path('Models/'))) {
            return $this->rootNamespace() . 'Models\\Model';
        }

        return $this->rootNamespace() . 'Model';
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The name of the model'],
        ];
    }
}
