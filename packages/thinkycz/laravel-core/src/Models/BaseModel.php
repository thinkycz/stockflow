<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Models;

use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Thinkycz\LaravelCore\Traits\ModelTrait;

class BaseModel extends IlluminateModel
{
    use ModelTrait;

    /**
     * @inheritDoc
     */
    public $preventsLazyLoading = true;

    /**
     * @inheritDoc
     */
    protected $guarded = ['id'];
}
