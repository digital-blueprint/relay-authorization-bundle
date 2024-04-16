<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="authorization_authorized_entities")
 */
class AuthorizedEntity
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=50)
     *
     * @Groups({"AuthorizationAuthorizedEntity:output"})
     */
    private ?string $identifier = null;

    /**
     * @ORM\Column(name="resource_action_identifier", type="string", length=50)
     *
     * @Groups({"AuthorizationAuthorizedEntity:input", "AuthorizationAuthorizedEntity:output"})
     */
    private ?string $resourceActionIdentifier = null;

    /**
     * @ORM\Column(name="user_identifier", type="string", length=64)
     *
     * @Groups({"AuthorizationAuthorizedEntity:input", "AuthorizationAuthorizedEntity:output"})
     */
    private ?string $userIdentifier = null;

    /**
     * @ORM\Column(name="group_identifier", type="string", length=64)
     *
     * ## @Groups({"AuthorizationAuthorizedEntity:input", "AuthorizationAuthorizedEntity:output"})
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

    public function getResourceActionIdentifier(): ?string
    {
        return $this->resourceActionIdentifier;
    }

    public function setResourceActionIdentifier(?string $resourceActionIdentifier): void
    {
        $this->resourceActionIdentifier = $resourceActionIdentifier;
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
