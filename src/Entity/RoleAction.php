<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class RoleAction
{
    public const TABLE_NAME = 'authorization_role_actions';

    public const ROLE_IDENTIFIER_COLUMN = 'role_identifier';
    public const AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN_NAME = 'available_resource_class_action_identifier';

    #[ORM\Id]
    #[ORM\JoinColumn(
        name: self::ROLE_IDENTIFIER_COLUMN,
        referencedColumnName: Role::IDENTIFIER_COLUMN_NAME,
        onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'actions')]
    private ?Role $role = null;

    #[ORM\Id]
    #[ORM\JoinColumn(
        name: self::AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN_NAME,
        referencedColumnName: AvailableResourceClassAction::IDENTIFIER_COLUMN_NAME,
        onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AvailableResourceClassAction::class, inversedBy: 'roleActions')]
    private ?AvailableResourceClassAction $availableResourceClassAction = null;

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): void
    {
        $this->role = $role;
    }

    public function getAvailableResourceClassAction(): ?AvailableResourceClassAction
    {
        return $this->availableResourceClassAction;
    }

    public function setAvailableResourceClassAction(?AvailableResourceClassAction $availableResourceClassAction): void
    {
        $this->availableResourceClassAction = $availableResourceClassAction;
    }
}
