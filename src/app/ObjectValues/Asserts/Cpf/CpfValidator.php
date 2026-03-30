<?php

namespace App\ObjectValues\Asserts\Cpf;

use App\Rules\CpfValidationRule;
use InvalidArgumentException;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};

class CpfValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(empty($value)){
            throw new InvalidArgumentException('O CPF deve ser informado.');
        }

        if (!CpfValidationRule::isValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
