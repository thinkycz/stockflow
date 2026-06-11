<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http;

class ApiFormRequest extends SecureFormRequest
{
    /**
     * Fluent validity builder.
     */
    public function builder(): ApiFormRequestValidityBuilder
    {
        return new ApiFormRequestValidityBuilder($this);
    }

    /**
     * @inheritDoc
     */
    public function validateResolved(): void {}
}
