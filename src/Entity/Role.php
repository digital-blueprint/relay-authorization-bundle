<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

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

    #[ORM\OneToMany(targetEntity: RoleName::class, mappedBy: 'role')]
    private Collection $names;

    #[ORM\OneToMany(targetEntity: AvailableResourceClassAction::class, mappedBy: 'role')]
    private Collection $actions;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'role')]
    private Collection $grants;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getNames(): Collection
    {
        return $this->names;
    }

    public function setNames(Collection $names): void
    {
        $this->names = $names;
    }

    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function setActions(Collection $actions): void
    {
        $this->actions = $actions;
    }

    public function getGrants(): Collection
    {
        return $this->grants;
    }

    public function setGrants(Collection $grants): void
    {
        $this->grants = $grants;
    }
}
