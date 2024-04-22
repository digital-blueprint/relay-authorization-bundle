<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="authorization_resource_action_grants")
 */
class ResourceActionGrant
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="relay_authorization_uuid_binary", unique=true)
     *
     * @Groups({"AuthorizationResourceActionGrant:output"})
     */
    private ?string $identifier = null;

    /**
     * @ORM\ManyToOne(targetEntity="Resource")
     *
     * @ORM\JoinColumn(name="authorization_resource_identifier", referencedColumnName="identifier")
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?Resource $resource = null;

    /**
     * @ORM\Column(name="action", type="string", length=40)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $action = null;

    /**
     * User type grant holder.
     *
     * @ORM\Column(name="user_identifier", type="string", length=40, nullable=true)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $userIdentifier = null;

    /**
     * Group type grant holder.
     *
     * @ORM\OneToOne(targetEntity="Group")
     *
     * @ORM\JoinColumn(name="group_identifier", referencedColumnName="identifier", nullable=true)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?Group $group = null;

    /**
     * Pre-defined group type grant holder.
     *
     * @ORM\Column(name="predefined_group_identifier", type="string", length=40, nullable=true)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
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

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setResource(?Resource $resource): void
    {
        $this->resource = $resource;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): void
    {
        $this->group = $group;
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
