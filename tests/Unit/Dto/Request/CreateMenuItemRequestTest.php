<?php

declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Dto\Request;

use Danilovl\MenuBuilderBundle\Dto\Request\CreateMenuItemRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateMenuItemRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testDefaultPositionIsNull(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test'
        );

        $this->assertNull($request->position);
        $data = $request->toArray();
        $this->assertArrayHasKey('position', $data);
        $this->assertNull($data['position']);
    }

    public function testExplicitPositionIsPreserved(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            position: 10
        );

        $this->assertSame(10, $request->position);
        $data = $request->toArray();
        $this->assertSame(10, $data['position']);
    }

    public function testSafeUriAndTranslationsPassValidation(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            uri: 'https://example.com',
            translations: [
                'cs' => ['label' => 'Test', 'uri' => '/cs'],
                'en' => ['label' => 'Test']
            ]
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(0, $violations);
    }

    public function testUnsafeUriIsRejected(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            uri: "java\nscript:alert(1)"
        );

        $violations = $this->validator->validate($request);
        $propertyPath = $violations->get(0)->getPropertyPath();

        $this->assertCount(1, $violations);
        $this->assertSame('uri', $propertyPath);
    }

    public function testUnsafeTranslationUriIsRejected(): void
    {
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            uri: '/ok',
            translations: ['cs' => ['uri' => 'data:text/html,<script>alert(1)</script>']]
        );

        $violations = $this->validator->validate($request);
        $propertyPath = $violations->get(0)->getPropertyPath();

        $this->assertCount(1, $violations);
        $this->assertSame('translations[cs][uri]', $propertyPath);
    }

    public function testTooLongUriIsRejected(): void
    {
        $uri = '/' . str_repeat('a', 1_024);
        $request = new CreateMenuItemRequest(
            menuName: 'main',
            label: 'Test',
            uri: $uri
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(1, $violations);
    }
}
