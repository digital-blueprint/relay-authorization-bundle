<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
class DynamicGroup
{
    #[Groups(['AuthorizationDynamicGroup:output'])]
    private ?string $identifier = null;

    public function __construct(?string $identifier = null)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }
}
