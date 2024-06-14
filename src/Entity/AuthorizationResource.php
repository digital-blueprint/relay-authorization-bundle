<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ORM\Table(name: 'authorization_resources')]
#[ORM\Entity]
class AuthorizationResource
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    #[Groups(['AuthorizationResource:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'resource_class', type: 'string', length: 40)]
    #[Groups(['AuthorizationResource:input', 'AuthorizationResource:output'])]
    private ?string $resourceClass = null;

    #[ORM\Column(name: 'resource_identifier', type: 'binary', length: 16, nullable: true)]
    #[Groups(['AuthorizationResource:input', 'AuthorizationResource:output'])]
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
