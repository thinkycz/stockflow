<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Models;

use Illuminate\Database\Eloquent\Relations\Pivot as IlluminatePivot;
use Thinkycz\LaravelCore\Support\Panicker;
use Thinkycz\LaravelCore\Traits\ModelTrait;

class Pivot extends IlluminatePivot
{
    use ModelTrait;

    /**
     * @inheritDoc
     */
    public $incrementing = true;

    /**
     * @inheritDoc
     */
    public $preventsLazyLoading = true;

    /**
     * @inheritDoc
     */
    public function delete(): int
    {
        if ($this->exists === false) {
            Panicker::panic('model not exists');
        }

        $ok = parent::delete();

        if ($ok !== 1) {
            Panicker::panic('model not deleted correctly');
        }

        return $ok;
    }
}
