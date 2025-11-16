<?php

namespace App\ObjectValues\Asserts\Cnpj;

use App\Rules\CnpjValidationRule;
use InvalidArgumentException;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};

class CnpjValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(empty($value)){
            throw new InvalidArgumentException('O CNPJ deve ser informado.');
        }

        if (!CnpjValidationRule::isValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
