<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

class Group
{
    /**
     * @Groups({"AuthorizationGroup:output"})
     */
    private ?string $identifier = null;

    /**
     * @Groups({"AuthorizationGroup:output", "AuthorizationGroup:input"})
     */
    private ?string $name = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
