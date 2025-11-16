<?php

namespace App\ObjectValues\Asserts\CpfCnpj;

use Symfony\Component\Validator\Constraint;

class CpfCnpj extends Constraint
{
    public $message = 'O valor "{{ value }}" não é um CPF/CNPJ válido.';
}
