<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ApiResource(
    shortName: 'AuthorizationGrantedActions',
    operations: [
        new GetCollection(
            uriTemplate: '/authorization/granted-actions',
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
                    new Parameter(
                        name: 'resourceType',
                        in: 'query',
                        description: 'The resource type to get grants for: 0 = RESOURCE_RESOURCE_TYPE, 1 = RESOURCE_GROUP_RESOURCE_TYPE (default: 0)',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'enum' => [
                                AuthorizationService::RESOURCE_RESOURCE_TYPE,
                                AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
                            ],
                        ],
                    ),
                ],
            ),
            provider: GrantedActionsProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationGrantedActions:output'],
    ],
)]
class GrantedActions
{
    #[ApiProperty(identifier: true)]
    #[Groups(['AuthorizationGrantedActions:output'])]
    private ?string $resourceClass = null;

    #[ApiProperty(identifier: true)]
    #[Groups(['AuthorizationGrantedActions:output'])]
    private ?string $resourceIdentifier = null;

    #[ApiProperty(identifier: true)]
    #[Groups(['AuthorizationGrantedActions:output'])]
    private ?int $resourceType = null;

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationGrantedActions:output'])]
    private array $actions = [];

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

    public function getResourceType(): ?int
    {
        return $this->resourceType;
    }

    public function setResourceType(?int $resourceType): void
    {
        $this->resourceType = $resourceType;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    #[Ignore]
    public function addAction(string $action): void
    {
        if ([AuthorizationService::MANAGE_ACTION] !== $this->actions) {
            if (AuthorizationService::MANAGE_ACTION === $action) {
                $this->actions = [AuthorizationService::MANAGE_ACTION];
            } else {
                $this->actions[] = $action;
            }
        }
    }
}
