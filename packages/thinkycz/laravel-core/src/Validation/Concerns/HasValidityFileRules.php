<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Illuminate\Validation\Rules\Dimensions;
use Thinkycz\LaravelCore\Support\Typer;

trait HasValidityFileRules
{
    /**
     * Add dimensions rule.
     *
     * @return $this
     */
    public function dimensionsRule(
        int|null $width = null,
        int|null $height = null,
        int|null $minWidth = null,
        int|null $maxWidth = null,
        int|null $minHeight = null,
        int|null $maxHeight = null,
        float|null $ratio = null,
    ): static {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $dimensions = new Dimensions([]);

        if ($width !== null) {
            $dimensions->width($width);
        }

        if ($height !== null) {
            $dimensions->height($height);
        }

        if ($minWidth !== null) {
            $dimensions->minWidth($minWidth);
        }

        if ($maxWidth !== null) {
            $dimensions->maxWidth($maxWidth);
        }

        if ($minHeight !== null) {
            $dimensions->minHeight($minHeight);
        }

        if ($maxHeight !== null) {
            $dimensions->maxHeight($maxHeight);
        }

        if ($ratio !== null) {
            $dimensions->ratio($ratio);
        }

        return $this->addRule($dimensions);
    }

    /**
     * Add file rule.
     *
     * @param ?array<int, string> $mimetypes
     *
     * @return $this
     */
    public function file(int|null $max, array|null $mimetypes): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->file = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->boolean === false && $this->integer === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        if ($mimetypes !== null) {
            $this->mimetypes($mimetypes);
        }

        if ($max !== null) {
            $this->max($max);
        }

        return $this;
    }

    /**
     * Add mimetypes rule.
     *
     * @param array<int, string> $mimetypes
     *
     * @return $this
     */
    public function mimetypes(array $mimetypes): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('mimetypes', $mimetypes);
    }

    /**
     * Add mimes rule.
     *
     * @param array<int, string> $mimes
     *
     * @return $this
     */
    public function mimes(array $mimes): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('mimes', $mimes);
    }

    /**
     * Add image rule.
     *
     * @param ?array<int, string> $mimeTypes
     *
     * @return $this
     */
    public function image(int|null $max, array|null $mimeTypes): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->file(
            $max ?? 10240,
            $mimeTypes ?? ['image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/svg', 'image/webp', 'image/bmp', 'image/x-bmp', 'image/x-ms-bmp', 'image/heif', 'image/heic'],
        );
    }

    /**
     * Add video rule.
     *
     * @param ?array<int, string> $mimeTypes
     *
     * @return $this
     */
    public function video(int|null $max, array|null $mimeTypes): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->file($max ?? 10240, $mimeTypes ?? ['video/mp4', 'video/mpeg', 'video/ogg', 'video/quicktime', 'video/webm']);
    }
}
