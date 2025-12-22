<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'AuthorizationResourceActionGrant',
    operations: [
        new Get(
            uriTemplate: '/authorization/resource-action-grants/{identifier}',
            openapi: new Operation(
                tags: ['Authorization'],
            ),
            provider: ResourceActionGrantProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/resource-action-grants',
            openapi: new Operation(
                tags: ['Authorization'],
                parameters: [
                    new Parameter(
                        name: 'resourceClass',
                        in: 'query',
                        description: 'The resource class to get grants for',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'resourceIdentifier',
                        in: 'query',
                        description: 'The resource identifier to get grants for',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            provider: ResourceActionGrantProvider::class,
        ),
        new Post(
            uriTemplate: '/authorization/resource-action-grants',
            openapi: new Operation(
                tags: ['Authorization'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'resourceClass' => [
                                        'type' => 'string',
                                        'description' => 'The resource class to grant the action on (to be used in combination with resourceIdentifier, in case authorizationResource is not set)',
                                        'example' => 'VendorBundleNameResourceName',
                                    ],
                                    'resourceIdentifier' => [
                                        'type' => 'string',
                                        'description' => 'The resource identifier to grant the action on (to be used in combination with resourceClass, in case authorizationResource is not set)',
                                        'example' => '01963da9-548b-7ca1-88e1-032ef6c1d992',
                                    ],
                                    'action' => [
                                        'type' => 'string',
                                        'description' => 'The action to grant',
                                        'example' => 'read',
                                    ],
                                    'userIdentifier' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the user (person) type grant holder',
                                        'example' => '811EC3ACC0ADCA70', // woody007
                                    ],
                                    'group' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the AuthorizationGroup type grant holder',
                                        'example' => '/authorization/groups/0193cf2d-89a8-7a9c-b317-2e5201afdd8d',
                                    ],
                                    'dynamicGroupIdentifier' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the AuthorizationDynamicGroup type grant holder',
                                        'example' => 'everybody',
                                    ],
                                ],
                                'required' => ['action'],
                            ],
                            'example' => [
                                'resourceClass' => 'VendorBundleNameResourceName',
                                'resourceIdentifier' => '01963da9-548b-7ca1-88e1-032ef6c1d992',
                                'action' => 'read',
                                'userIdentifier' => '811EC3ACC0ADCA70', // woody007
                            ],
                        ],
                    ]),
                ),
            ),
            processor: ResourceActionGrantProcessor::class
        ),
        new Delete(
            uriTemplate: '/authorization/resource-action-grants/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: ResourceActionGrantProvider::class,
            processor: ResourceActionGrantProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationResourceActionGrant:output'],
    ],
    denormalizationContext: [
        'groups' => ['AuthorizationResourceActionGrant:input'],
    ],
)]
#[ORM\Table(name: 'authorization_resource_action_grants')]
#[ORM\Entity]
class ResourceActionGrant
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationResourceActionGrant:output'])]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'authorization_resource_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $authorizationResource = null;

    #[ORM\Column(name: 'action', type: 'string', length: 40)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $action = null;

    /**
     * User type grant holder.
     */
    #[ApiProperty(
        description: 'The user type type grant holder',
        openapiContext: [
            'example' => '0193cf3e-21d5-72cf-9734-14ce2768f49e',
        ]
    )]
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type grant holder.
     */
    #[ApiProperty(
        description: 'The AuthorizationGroup type grant holder',
        openapiContext: [
            'example' => '/authorization/groups/0193cf2d-89a8-7a9c-b317-2e5201afdd8d',
        ]
    )]
    #[ORM\JoinColumn(name: 'group_identifier', referencedColumnName: 'identifier', nullable: true, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?Group $group = null;

    /**
     * Pre-defined group type grant holder.
     */
    #[ApiProperty(
        description: 'AuthorizationDynamicGroup type grant holder',
        openapiContext: [
            'example' => 'students',
        ]
    )]
    #[ORM\Column(name: 'dynamic_group_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $dynamicGroupIdentifier = null;

    // #[ORM\Column(name: 'shareable', type: 'boolean', nullable: false, options: ['default' => false])]
    // #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private bool $shareable = false;

    /**
     * If set, $this was/is requested to be created as a share of $shareOf.
     */
    // #[ORM\JoinColumn(name: 'share_of_identifier', referencedColumnName: 'identifier', nullable: true, onDelete: 'CASCADE')]
    // #[ORM\ManyToOne(targetEntity: self::class)]
    // #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?ResourceActionGrant $shareOf = null;

    // #[ORM\Column(name: 'creator_id', type: 'string', length: 40, nullable: true)]
    private ?string $creatorId = null;

    // #[ORM\Column(name: 'date_created', type: 'datetime', nullable: true)]
    private ?\DateTime $dateCreated = null;

    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $resourceClass = null;

    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
    private ?string $resourceIdentifier = null;

    #[Groups(['AuthorizationResourceActionGrant:output'])]
    private ?array $grantedActions = null;

    private ?string $authorizationResourceIdentifier = null;

    private bool $isInherited = false;

    public function getIdentifier(): ?string
    {
        return $this->isInherited ? $this->identifier.'_inherited' : $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * NOTE: The authorization resource is not set (hydrated) by default, so its presence is not guaranteed.
     * Try to use getResourceClass() and getResourceIdentifier(), or getAuthorizationResourceIdentifier()
     * to identify the resource instead.
     */
    public function getAuthorizationResource(): ?AuthorizationResource
    {
        return $this->authorizationResource;
    }

    public function setAuthorizationResource(?AuthorizationResource $authorizationResource): void
    {
        $this->authorizationResource = $authorizationResource;
    }

    /**
     * Can be used for cases where authorizationResource is not hydrated automatically, i.e. in custom sql queries.
     */
    public function setAuthorizationResourceIdentifier(?string $authorizationResourceIdentifier): void
    {
        assert($this->authorizationResource === null);
        $this->authorizationResourceIdentifier = $authorizationResourceIdentifier;
    }

    public function getAuthorizationResourceIdentifier(): ?string
    {
        if ($this->authorizationResource !== null) {
            return $this->authorizationResource->getIdentifier();
        }

        return $this->authorizationResourceIdentifier;
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
        return $this->authorizationResource?->getResourceClass() ?? $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->authorizationResource?->getResourceIdentifier() ?? $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }

    public function getShareable(): bool
    {
        return $this->shareable;
    }

    public function setShareable(bool $shareable): void
    {
        $this->shareable = $shareable;
    }

    public function getShareOf(): ?self
    {
        return $this->shareOf;
    }

    public function setShareOf(?self $shareOf): void
    {
        $this->shareOf = $shareOf;
    }

    public function setGrantedActions(array $grantedActions): void
    {
        $this->grantedActions = $grantedActions;
    }

    public function getGrantedActions(): ?array
    {
        return $this->grantedActions;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function isInherited(): bool
    {
        return $this->isInherited;
    }

    public function setIsInherited(bool $isInherited): void
    {
        $this->isInherited = $isInherited;
    }

    public function __toString(): string
    {
        return sprintf(
            'ResourceActionGrant{class=%s, identifier=%s, action=%s, user=%s, group=%s, dynamicGroup=%s}',
            $this->getResourceClass(),
            $this->getResourceIdentifier(),
            $this->getAction(),
            $this->getUserIdentifier(),
            $this->getGroup() ? $this->getGroup()->getName() : 'null',
            $this->getDynamicGroupIdentifier() ?? 'null'
        );
    }
}
