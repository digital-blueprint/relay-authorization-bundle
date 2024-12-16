<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
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

    #[ApiProperty(openapiContext: [
        'description' => 'The parent AuthorizationGroup',
        'example' => '/authorization/groups/0193cf2d-89a8-7a9c-b317-2e5201afdd8d',
    ])]
    #[ORM\JoinColumn(name: 'parent_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'members')]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output'])]
    private ?Group $group = null;

    /**
     * User type member.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The user type group member',
        'example' => '0193cf3e-21d5-72cf-9734-14ce2768f49e',
    ])]
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output', 'AuthorizationGroup:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type member.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The AuthorizationGroup type group member',
        'example' => '/authorization/groups/0193cf2f-8c29-7683-80c9-b2cf1e1bd77f',
    ])]
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
