<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Validator\Constraints;

use Danilovl\MenuBuilderBundle\Validator\Constraints\{
    SafeUri,
    SafeUriValidator
};
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\{
    UnexpectedTypeException,
    UnexpectedValueException
};
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<SafeUriValidator>
 */
final class SafeUriValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SafeUriValidator
    {
        return new SafeUriValidator;
    }

    public function testNullAndEmptyAreValid(): void
    {
        $constraint = new SafeUri;

        $this->validator->validate(null, $constraint);
        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testSafeUriIsValid(): void
    {
        $constraint = new SafeUri;

        $this->validator->validate('https://example.com', $constraint);

        $this->assertNoViolation();
    }

    public function testUnsafeUriAddsViolation(): void
    {
        $constraint = new SafeUri;

        $this->validator->validate("java\tscript:alert(1)", $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ scheme }}', 'javascript')
            ->assertRaised();
    }

    public function testCustomMessage(): void
    {
        $constraint = new SafeUri(message: 'custom');

        $this->validator->validate('ftp://x', $constraint);

        $this->buildViolation('custom')
            ->setParameter('{{ scheme }}', 'ftp')
            ->assertRaised();
    }

    public function testNonStringValueThrows(): void
    {
        $constraint = new SafeUri;

        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(123, $constraint);
    }

    public function testWrongConstraintThrows(): void
    {
        $constraint = new NotBlank;

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('x', $constraint);
    }
}
