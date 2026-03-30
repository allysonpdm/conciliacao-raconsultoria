<?php

namespace App\ObjectValues\Contracts;

use Stringable;

abstract class ObjectValue implements Stringable
{
    protected mixed $value;

    public function __construct($value = null)
    {
        $this->value = $value;
        $this->validate();
    }

    protected abstract function validate(): void;
    public abstract function __toString();

    public function getValue(): mixed
    {
        return $this->value;
    }
}
