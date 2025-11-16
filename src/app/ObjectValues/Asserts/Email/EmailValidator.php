<?php

namespace App\ObjectValues\Asserts\Email;

use App\Rules\{
    EmailMxValidationRule
};
use InvalidArgumentException;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};

class EmailValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(
            !is_string($value) ||
            empty($value)
        ){
            throw new InvalidArgumentException('Um e-mail deve ser informado');
        }

        if (!EmailMxValidationRule::isValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
