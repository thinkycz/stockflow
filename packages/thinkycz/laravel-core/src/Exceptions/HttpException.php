<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Throwable;

class HttpException extends SymfonyHttpException
{
    /**
     * @inheritDoc
     *
     * @param array<mixed> $headers
     * @param array<mixed> $data
     */
    public function __construct(int $statusCode, string $message = '', Throwable|null $previous = null, array $headers = [], int $code = 0, public array $data = [])
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
