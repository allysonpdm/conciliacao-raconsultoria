<?php

namespace App\ObjectValues\Asserts\CpfCnpj;

use App\Rules\{
    CnpjValidationRule,
    CpfValidationRule
};
use InvalidArgumentException;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};

class CpfCnpjValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(empty($value)){
            throw new InvalidArgumentException('O CPF/CNPJ deve ser informado.');
        }

        if (
            !CpfValidationRule::isValid($value) &&
            !CnpjValidationRule::isValid($value)
        ) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
