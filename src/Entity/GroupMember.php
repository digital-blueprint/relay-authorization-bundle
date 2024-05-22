<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ORM\Table(name: 'authorization_group_members')]
#[ORM\Entity]
class GroupMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationGroupMember:output'])]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'parent_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'members')]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output'])]
    private ?Group $group = null;

    /**
     * User type member.
     */
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output', 'AuthorizationGroup:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type member.
     */
    #[ORM\JoinColumn(name: 'child_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output', 'AuthorizationGroup:output'])]
    private ?Group $childGroup = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): void
    {
        $this->group = $group;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getChildGroup(): ?Group
    {
        return $this->childGroup;
    }

    public function setChildGroup(?Group $childGroup): void
    {
        $this->childGroup = $childGroup;
    }
}
