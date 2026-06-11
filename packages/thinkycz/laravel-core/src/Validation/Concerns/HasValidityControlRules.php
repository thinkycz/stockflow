<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Closure;

trait HasValidityControlRules
{
    /**
     * Conditionally add rule.
     *
     * @param Closure(static): void $closure
     *
     * @return $this
     */
    public function when(bool $condition, Closure $closure): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if ($condition) {
            $closure($this);
        }

        return $this;
    }

    /**
     * Skip next rule.
     *
     * @return $this
     */
    public function skipNext(): static
    {
        $this->skipNext = true;

        return $this;
    }

    /**
     * Skip next rule if flag not true.
     *
     * @return $this
     */
    public function if(bool $flag): static
    {
        if (!$flag) {
            return $this->skipNext();
        }

        return $this;
    }

    /**
     * Skip next rule if flag true.
     *
     * @return $this
     */
    public function ifNot(bool $flag): static
    {
        if ($flag) {
            return $this->skipNext();
        }

        return $this;
    }

    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param Closure(static): void $callback
     *
     * @return $this
     */
    public function tap(Closure $callback): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $callback($this);

        return $this;
    }

    /**
     * Mark as unsafe.
     *
     * @return $this
     */
    public function unsafe(bool $flag = true): static
    {
        $this->unsafe = $flag;

        return $this;
    }

    /**
     * If then required.
     */
    public function ifThenRequired(bool $flag): static
    {
        return $this->if($flag)->required();
    }

    /**
     * If then filled.
     */
    public function ifThenFilled(bool $flag): static
    {
        return $this->if($flag)->filled();
    }

    /**
     * If then present.
     */
    public function ifThenPresent(bool $flag): static
    {
        return $this->if($flag)->present();
    }

    /**
     * If then prohibited.
     */
    public function ifThenProhibited(bool $flag): static
    {
        return $this->if($flag)->prohibited();
    }

    /**
     * If then missing.
     */
    public function ifThenMissing(bool $flag): static
    {
        return $this->if($flag)->missing();
    }

    /**
     * If then accepted.
     */
    public function ifThenAccepted(bool $flag): static
    {
        return $this->if($flag)->accepted();
    }

    /**
     * If then declined.
     */
    public function ifThenDeclined(bool $flag): static
    {
        return $this->if($flag)->declined();
    }
}
