<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class Role
{
    public const TABLE_NAME = 'authorization_roles';

    public const IDENTIFIER_COLUMN_NAME = 'identifier';

    #[ORM\Id]
    #[ORM\Column(name: self::IDENTIFIER_COLUMN_NAME, type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\OneToMany(targetEntity: RoleName::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roleNames;

    #[ORM\OneToMany(targetEntity: RoleAction::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roleActions;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $resourceActionGrants;

    public function __construct()
    {
        $this->roleNames = new ArrayCollection();
        $this->roleActions = new ArrayCollection();
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

    public function getRoleNames(): Collection
    {
        return $this->roleNames;
    }

    public function setRoleNames(Collection $roleNames): void
    {
        $this->roleNames = $roleNames;
    }

    public function getRoleActions(): Collection
    {
        return $this->roleActions;
    }

    public function setRoleActions(Collection $roleActions): void
    {
        $this->roleActions = $roleActions;
    }

    public function getResourceActionGrants(): Collection
    {
        return $this->resourceActionGrants;
    }

    public function setResourceActionGrants(Collection $resourceActionGrants): void
    {
        $this->resourceActionGrants = $resourceActionGrants;
    }
}
