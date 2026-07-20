<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class AuthorizationResource
{
    public const TABLE_NAME = 'authorization_resources';
    public const IDENTIFIER_COLUMN = 'identifier';
    public const RESOURCE_CLASS_COLUMN = 'resource_class';
    public const RESOURCE_IDENTIFIER_COLUMN = 'resource_identifier';
    public const RESOURCE_TYPE_COLUMN = 'resource_type';

    public const RESOURCE_RESOURCE_TYPE = 0;
    public const RESOURCE_GROUP_RESOURCE_TYPE = 1;

    #[ORM\Id]
    #[ORM\Column(name: self::IDENTIFIER_COLUMN, type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(name: self::RESOURCE_CLASS_COLUMN, type: 'string', length: 40)]
    private ?string $resourceClass = null;

    #[ORM\Column(name: self::RESOURCE_IDENTIFIER_COLUMN, type: 'string', length: 40, nullable: true)]
    private ?string $resourceIdentifier = null;

    #[ORM\Column(name: self::RESOURCE_TYPE_COLUMN, type: 'smallint', nullable: false, options: ['default' => self::RESOURCE_RESOURCE_TYPE])]
    private int $resourceType = self::RESOURCE_RESOURCE_TYPE;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'authorizationResource')]
    private Collection $resourceActionGrants;

    public function __construct()
    {
        $this->resourceActionGrants = new ArrayCollection();
    }

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

    public function getResourceActionGrants(): Collection
    {
        return $this->resourceActionGrants;
    }

    public function setResourceActionGrants(Collection $resourceActionGrants): void
    {
        $this->resourceActionGrants = $resourceActionGrants;
    }

    public function getResourceType(): int
    {
        return $this->resourceType;
    }

    public function setResourceType(int $resourceType): void
    {
        $this->resourceType = $resourceType;
    }
}
