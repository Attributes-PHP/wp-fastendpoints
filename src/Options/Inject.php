<?php

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Options;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Inject
{
    protected ?string $name;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
