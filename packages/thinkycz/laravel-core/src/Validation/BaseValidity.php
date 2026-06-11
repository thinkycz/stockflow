<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

class BaseValidity
{
    /**
     * Make validity instance.
     */
    public function make(): Validity
    {
        return Validity::make();
    }

    /**
     * Signature validation rules.
     */
    public function signature(): Validity
    {
        return $this->make()->string(null);
    }

    /**
     * Expires validation rules.
     */
    public function expires(): Validity
    {
        return $this->make()->integer(null, null);
    }

    /**
     * Cursor validation rules.
     */
    public function cursor(): Validity
    {
        return $this->make()->string(null)->cursor();
    }

    /**
     * Page validation rules.
     */
    public function page(): Validity
    {
        return $this->make()->positive(null, null);
    }

    /**
     * Take validation rules.
     */
    public function take(int $max): Validity
    {
        return $this->make()->positive($max, null);
    }

    /**
     * Filter validation rules.
     */
    public function filter(): Validity
    {
        return $this->make()->array(null);
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->make()->positive(null, null);
    }

    /**
     * Slug validation rules.
     */
    public function slug(): Validity
    {
        return $this->make()->string(null);
    }

    /**
     * Mode validation rules.
     *
     * @param array<string> $modes
     */
    public function mode(array $modes): Validity
    {
        return $this->make()->inString($modes);
    }

    /**
     * Search validation rules.
     */
    public function search(): Validity
    {
        return $this->make()->varchar();
    }

    /**
     * Sort validation rules.
     *
     * @param array<string> $sort
     */
    public function sort(array $sort): Validity
    {
        return $this->make()->inString($sort);
    }

    /**
     * Date validation rules.
     */
    public function date(): Validity
    {
        return $this->make()->string(null)->dateFormat();
    }

    /**
     * Datetime validation rules.
     */
    public function dateTime(): Validity
    {
        return $this->make()->string(null)->dateFormat('Y-m-d\\TH:i:s.u\\Z');
    }

    /**
     * Collection validation rules.
     */
    public function collection(): Validity
    {
        return $this->make()->collection(null);
    }

    /**
     * Array validation rules.
     */
    public function array(): Validity
    {
        return $this->make()->array(null);
    }
}
