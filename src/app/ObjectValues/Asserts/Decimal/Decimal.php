<?php

namespace App\ObjectValues\Asserts\Decimal;

use Symfony\Component\Validator\Constraint;

class Decimal extends Constraint
{
    public $message = 'O valor "{{ value }}" não é um decimal válido.';

    public function __construct(
        public int $decimalPlaces = 2,
        array $options = null,
        array $groups = null,
        mixed $payload = null
    )
    {
        parent::__construct($options, $groups, $payload);
    }

}
