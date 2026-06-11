<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Thinkycz\LaravelCore\Validation\SecureValidationFactory;

class SecureFormRequest extends FormRequest
{
    /**
     * @inheritDoc
     */
    public function validatorFactory(): ValidatorFactory
    {
        return new SecureValidationFactory(parent::validatorFactory());
    }

    /**
     * @inheritDoc
     */
    protected function getValidatorInstance(): Validator
    {
        if ($this->validator === null) {
            $this->validator = $this->createDefaultValidator($this->validatorFactory());
        }

        return $this->validator;
    }
}
