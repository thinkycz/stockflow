<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Thinkycz\LaravelCore\Support\Typer;

class UserResource extends JsonApiResource
{
    /**
     * Get the resource's ID.
     */
    public function toId(Request $request): string
    {
        $resource = Typer::assertInstance($this->resource, User::class);

        return (string) $resource->getKey();
    }

    /**
     * Get the resource's type.
     */
    public function toType(Request $request): string
    {
        return 'users';
    }

    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $resource = Typer::assertInstance($this->resource, User::class);

        return [
            'email' => $resource->getEmail(),
            'locale' => $resource->getLocale(),
            'email_verified_at' => $resource->getEmailVerifiedAt(),
            'created_at' => $resource->getCreatedAt(),
        ];
    }
}
