<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Thinkycz\LaravelCore\Support\Panicker;

class MakeCrudCommand extends GeneratorCommand
{
    /**
     * @inheritDoc
     */
    protected $name = 'make:crud';

    /**
     * @inheritDoc
     */
    protected $description = 'Create CRUD for a given model';

    /**
     * @inheritDoc
     */
    protected $type = 'Model';

    /**
     * @inheritDoc
     */
    public function handle(): bool
    {
        $modelName = $this->getNameInput();

        if ($this->isReservedName($modelName)) {
            $this->error(Panicker::message(__METHOD__, 'name is reserved by PHP', ['name' => $modelName]));

            return (bool) static::FAILURE;
        }

        $this->call('make:model', ['name' => $modelName, '--factory' => true, '--migration' => true]);

        $this->call('make:validity', ['name' => $modelName]);

        $this->call('make:crud-index', ['name' => $modelName]);

        $this->call('make:crud-show', ['name' => $modelName]);

        $this->call('make:crud-store', ['name' => $modelName]);

        $this->call('make:crud-update', ['name' => $modelName]);

        $this->call('make:crud-destroy', ['name' => $modelName]);

        $this->call('make:test-index', ['name' => $modelName]);

        $this->call('make:test-show', ['name' => $modelName]);

        $this->call('make:test-store', ['name' => $modelName]);

        $this->call('make:test-update', ['name' => $modelName]);

        $this->call('make:test-destroy', ['name' => $modelName]);

        $this->call('make:crud-routes', ['name' => $modelName]);

        return (bool) static::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    protected function getStub(): string
    {
        return '';
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
