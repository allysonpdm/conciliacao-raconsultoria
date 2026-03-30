<?php

namespace App\ObjectValues\Asserts\Cnpj;

use Symfony\Component\Validator\Constraint;

class Cnpj extends Constraint
{
    public $message = 'O valor "{{ value }}" não é um CNPJ válido.';
}
