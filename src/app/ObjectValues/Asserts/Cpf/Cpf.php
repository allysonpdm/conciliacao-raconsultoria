<?php

namespace App\ObjectValues\Asserts\Cpf;

use Symfony\Component\Validator\Constraint;

class Cpf extends Constraint
{
    public $message = 'O valor "{{ value }}" não é um CPF válido.';
}
