<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
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

    #[ApiProperty(openapiContext: [
        'description' => 'The parent AuthorizationResource',
        'example' => '/authorization/resources/0193cf30-6d41-7f19-b882-a18015b39270',
    ])]
    #[ORM\JoinColumn(name: 'authorization_resource_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?AuthorizationResource $authorizationResource = null;

    #[ApiProperty(openapiContext: [
        'description' => 'The action granted on the AuthorizationResource',
        'example' => 'read',
    ])]
    #[ORM\Column(name: 'action', type: 'string', length: 40)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $action = null;

    /**
     * User type grant holder.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The user type grant holder',
        'example' => '0193cf3e-21d5-72cf-9734-14ce2768f49e',
    ])]
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type grant holder.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The AuthorizationGroup type grant holder',
        'example' => '/authorization/groups/0193cf2d-89a8-7a9c-b317-2e5201afdd8d',
        ])]
    #[ORM\JoinColumn(name: 'group_identifier', referencedColumnName: 'identifier', nullable: true, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?Group $group = null;

    /**
     * Pre-defined group type grant holder.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The AuthorizationDynamicGroup type grant holder',
        'example' => 'students',
    ])]
    #[ORM\Column(name: 'dynamic_group_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $dynamicGroupIdentifier = null;

    #[Groups(['AuthorizationResourceActionGrant:input'])]
    private ?string $resourceClass = null;

    #[Groups(['AuthorizationResourceActionGrant:input'])]
    private ?string $resourceIdentifier = null;

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

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }
}
