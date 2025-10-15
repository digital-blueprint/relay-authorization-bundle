<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @internal
 */
#[ORM\Table(name: 'authorization_resources')]
#[ORM\Entity]
class AuthorizationResource
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(name: 'resource_class', type: 'string', length: 40)]
    private ?string $resourceClass = null;

    #[ORM\Column(name: 'resource_identifier', type: 'string', length: 40, nullable: true)]
    private ?string $resourceIdentifier = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }
}
