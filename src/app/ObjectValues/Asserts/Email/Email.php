<?php

namespace App\ObjectValues\Asserts\Email;

use Symfony\Component\Validator\Constraint;

class Email extends Constraint
{
    public $message = 'O email "{{ value }}" não tem um MX válido.';
}
