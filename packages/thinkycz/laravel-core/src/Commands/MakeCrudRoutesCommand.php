<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Thinkycz\LaravelCore\Support\Typer;

class MakeCrudRoutesCommand extends GeneratorCommand
{
    /**
     * @inheritDoc
     */
    protected $name = 'make:crud-routes';

    /**
     * @inheritDoc
     */
    protected $description = 'Create CRUD routes for a given model';

    /**
     * @inheritDoc
     */
    protected $type = 'Model';

    /**
     * @inheritDoc
     */
    public function handle(): bool
    {
        $path = $this->laravel->basePath('routes/api.php');

        if ($this->files->exists($path) === false) {
            $this->components->error('Routes file [routes/api.php] does not exist.');

            return (bool) static::FAILURE;
        }

        $namespaceModel = $this->option('model') !== null
            ? $this->qualifyModel(Typer::parseString($this->option('model')))
            : $this->qualifyModel(Typer::parseString($this->guessModelName($this->getNameInput())));

        $model = \class_basename($namespaceModel);

        $table = Str::of($model)->plural()->snake()->toString();

        $routes = $this->files->get($path);

        $stub = $this->files->get($this->getStub());

        $replace = [
            '{{ model }}' => $model,
            '{{ table }}' => $table,
        ];

        $stub = \str_replace(
            \array_keys($replace),
            \array_values($replace),
            $stub,
        );

        $routes = $routes . "\n" . $stub . "\n";

        $this->files->put($path, $routes);

        $this->components->info('Routes file [routes/api.php] modified successfully.');

        return (bool) static::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('stubs/crud.routes.stub');
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
     * Guess model name.
     */
    protected function guessModelName(string $name): string
    {
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
