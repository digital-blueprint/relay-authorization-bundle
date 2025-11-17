<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @internal
 */
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class AvailableResourceClassAction
{
    public const TABLE_NAME = 'authorization_available_resource_class_actions';
    public const IDENTIFIER_COLUMN_NAME = 'identifier';
    public const RESOURCE_CLASS_COLUMN_NAME = 'resource_class';
    public const ACTION_COLUMN_NAME = 'action';
    public const ACTION_TYPE_COLUMN_NAME = 'action_type';

    public const ITEM_ACTION_TYPE = 0;
    public const COLLECTION_ACTION_TYPE = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(name: 'resource_class', type: 'string', length: 40)]
    private ?string $resourceClass = null;

    #[ORM\Column(name: 'action', type: 'string', length: 40)]
    private ?string $action = null;

    #[ORM\Column(name: 'action_type', type: 'smallint')]
    private ?int $actionType = null;

    #[ORM\OneToMany(targetEntity: AvailableResourceClassActionName::class, mappedBy: 'availableResourceClassAction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $names;

    public function __construct()
    {
        $this->names = new ArrayCollection();
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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getActionType(): ?int
    {
        return $this->actionType;
    }

    public function setActionType(?int $actionType): void
    {
        $this->actionType = $actionType;
    }

    public function getNames(): Collection
    {
        return $this->names;
    }

    public function setNames(Collection $names): void
    {
        $this->names = $names;
    }
}
