<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Thinkycz\LaravelCore\Validation\Rules\CursorRule;

trait HasValidityDomainRules
{
    /**
     * Id rules.
     *
     * @return $this
     */
    public function id(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->unsignedBigInt();
    }

    /**
     * Slug rules.
     *
     * @return $this
     */
    public function slug(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->varchar();
    }

    /**
     * Id slug rules.
     *
     * @return $this
     */
    public function idSlug(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->varchar();
    }

    /**
     * Date time rules.
     *
     * @return $this
     */
    public function dateTime(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->string(null)->date();
    }

    /**
     * Cursor rules.
     *
     * @return $this
     */
    public function cursor(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->string(null)->addRule(new CursorRule());
    }
}
