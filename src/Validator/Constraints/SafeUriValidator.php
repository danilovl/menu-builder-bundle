<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Validator\Constraints;

use Danilovl\MenuBuilderBundle\Security\UriSafety;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};
use Symfony\Component\Validator\Exception\{
    UnexpectedTypeException,
    UnexpectedValueException
};

class SafeUriValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeUri) {
            throw new UnexpectedTypeException($constraint, SafeUri::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (UriSafety::isSafe($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ scheme }}', (string) UriSafety::extractScheme($value))
            ->addViolation();
    }
}
