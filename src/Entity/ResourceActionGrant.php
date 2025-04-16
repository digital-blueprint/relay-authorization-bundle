<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\ResourceActionGrantProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationResourceActionGrant',
    operations: [
        new Get(
            uriTemplate: '/authorization/resource-action-grants/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: ResourceActionGrantProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/resource-action-grants',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: ResourceActionGrantProvider::class,
            parameters: [
                'resourceClass' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                    ],
                    description: 'The resource class to get grants for',
                    required: false,
                ),
                'resourceIdentifier' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                    ],
                    description: 'The resource identifier to get grants for',
                    required: false,
                ),
            ]
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
                                    'authorizationResource' => [
                                        'type' => 'string',
                                        'description' => 'The AuthorizationResource to grant the action on',
                                        'example' => '/authorization/resources/{identifier}',
                                    ],
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
                                'authorizationResource' => '/authorization/resources/{identifier}',
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
    #[Groups(['AuthorizationResourceActionGrant:input', 'AuthorizationResourceActionGrant:output'])]
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
