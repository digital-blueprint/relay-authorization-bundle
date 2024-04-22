<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="authorization_group_members")
 */
class GroupMember
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="relay_authorization_uuid_binary", unique=true)
     *
     * @Groups({"AuthorizationGroupMember:output"})
     */
    private ?string $identifier = null;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="members")
     *
     * @ORM\JoinColumn(name="parent_group_identifier", referencedColumnName="identifier")
     *
     * @Groups({"AuthorizationGroupMember:input", "AuthorizationGroupMember:output"})
     */
    private ?Group $parentGroup = null;

    /**
     * User type member.
     *
     * @ORM\Column(name="user_identifier", type="string", length=40, nullable=true)
     *
     * @Groups({"AuthorizationGroupMember:input", "AuthorizationGroupMember:output"})
     */
    private ?string $userIdentifier = null;

    /**
     * Group type member.
     *
     * @ORM\OneToOne(targetEntity="Group")
     *
     * @ORM\JoinColumn(name="child_group_identifier", referencedColumnName="identifier")
     *
     * @Groups({"AuthorizationGroupMember:input", "AuthorizationGroupMember:output"})
     */
    private ?Group $childGroup = null;

    /**
     * Pre-defined group type member.
     *
     * @ORM\Column(name="predefined_group_identifier", type="string", length=40, nullable=true)
     *
     * @Groups({"AuthorizationGroupMember:input", "AuthorizationGroupMember:output"})
     */
    private ?string $predefinedGroupIdentifier = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getParentGroup(): ?Group
    {
        return $this->parentGroup;
    }

    public function setParentGroup(?Group $parentGroup): void
    {
        $this->parentGroup = $parentGroup;
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

    public function getPredefinedGroupIdentifier(): ?string
    {
        return $this->predefinedGroupIdentifier;
    }

    public function setPredefinedGroupIdentifier(?string $predefinedGroupIdentifier): void
    {
        $this->predefinedGroupIdentifier = $predefinedGroupIdentifier;
    }
}
