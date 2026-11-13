<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Dto\Request;

use Danilovl\MenuBuilderBundle\Dto\Request\UpdateMenuItemRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdateMenuItemRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testEmptyRequestIsValid(): void
    {
        $request = new UpdateMenuItemRequest;

        $violations = $this->validator->validate($request);
        $data = $request->toArray();

        $this->assertCount(0, $violations);
        $this->assertSame([], $data);
    }

    public function testUnsafeUriIsRejected(): void
    {
        $request = new UpdateMenuItemRequest(uri: 'vbscript:msgbox(1)');

        $violations = $this->validator->validate($request);
        $propertyPath = $violations->get(0)->getPropertyPath();

        $this->assertCount(1, $violations);
        $this->assertSame('uri', $propertyPath);
    }

    public function testUnsafeTranslationUriIsRejected(): void
    {
        $request = new UpdateMenuItemRequest(translations: ['en' => ['uri' => 'javascript:alert(1)']]);

        $violations = $this->validator->validate($request);
        $propertyPath = $violations->get(0)->getPropertyPath();

        $this->assertCount(1, $violations);
        $this->assertSame('translations[en][uri]', $propertyPath);
    }
}
