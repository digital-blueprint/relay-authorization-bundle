<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ORM\Table(name: 'authorization_resource_action_grants')]
#[ORM\Entity]
class ResourceActionGrant
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationResourceActionGrant:output'])]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'authorization_resource_identifier', referencedColumnName: 'identifier')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?AuthorizationResource $authorizationResource = null;

    #[ORM\Column(name: 'action', type: 'string', length: 40)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $action = null;

    /**
     * User type grant holder.
     */
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type grant holder.
     */
    #[ORM\JoinColumn(name: 'group_identifier', referencedColumnName: 'identifier', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?Group $group = null;

    /**
     * Pre-defined group type grant holder.
     */
    #[ORM\Column(name: 'dynamic_group_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $dynamicGroupIdentifier = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getAuthorizationResource(): ?AuthorizationResource
    {
        return $this->authorizationResource;
    }

    public function setAuthorizationResource(?AuthorizationResource $authorizationResource): void
    {
        $this->authorizationResource = $authorizationResource;
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

    public function getDynamicGroupIdentifier(): ?string
    {
        return $this->dynamicGroupIdentifier;
    }

    public function setDynamicGroupIdentifier(?string $dynamicGroupIdentifier): void
    {
        $this->dynamicGroupIdentifier = $dynamicGroupIdentifier;
    }
}
