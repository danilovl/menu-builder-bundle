<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class SafeUri extends Constraint
{
    public string $message = 'URI scheme "{{ scheme }}" is not allowed.';

    /**
     * @param array<int, string>|null $groups
     */
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
