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
     * @ORM\Column(name="authorization_resource_identifier", type="relay_authorization_uuid_binary")
     *
     * @Groups({"AuthorizationResourceActionGrant:input", "AuthorizationResourceActionGrant:output"})
     */
    private ?string $authorizationResourceIdentifier = null;

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
     * @ORM\Column(name="group_identifier", type="relay_authorization_uuid_binary")
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

    public function getAuthorizationResourceIdentifier(): ?string
    {
        return $this->authorizationResourceIdentifier;
    }

    public function setAuthorizationResourceIdentifier(?string $authorizationResourceIdentifier): void
    {
        $this->authorizationResourceIdentifier = $authorizationResourceIdentifier;
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
