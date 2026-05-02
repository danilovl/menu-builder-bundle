<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

readonly class RenameMenuRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name
    ) {}
}
