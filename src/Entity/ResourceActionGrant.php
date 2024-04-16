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
     * @ORM\Column(name="namespace", type="string", length=40)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $namespace = null;

    /**
     * @ORM\Column(name="resource_identifier", type="string", length=40)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $resourceIdentifier = null;

    /**
     * @ORM\Column(name="action", type="string", length=40)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $action = null;

    /**
     * @ORM\Column(name="user_identifier", type="string", length=40)
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $userIdentifier = null;

    /**
     * @ORM\Column(type="relay_authorization_uuid_binary")
     *
     * ## @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $groupIdentifier = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setNamespace(?string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
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

    public function getGroupIdentifier(): ?string
    {
        return $this->groupIdentifier;
    }

    public function setGroupIdentifier(?string $groupIdentifier): void
    {
        $this->groupIdentifier = $groupIdentifier;
    }
}
