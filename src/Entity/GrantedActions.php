<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ApiResource(
    shortName: 'AuthorizationGrantedActions',
    operations: [
        new Get(
            uriTemplate: '/authorization/granted-actions/{resourceClass}/{resourceIdentifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: GrantedActionsProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/granted-actions/{resourceClass}',
            openapi: new Operation(
                tags: ['Authorization']
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

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationGrantedActions:output'])]
    private array $actions = [];

    /**
     * @var AvailableResourceClassAction[]
     */
    #[Groups(['AuthorizationGrantedActions:output'])]
    private array $otherResourceTypeActions = [];

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

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }

    public function getOtherResourceTypeActions(): ?array
    {
        return $this->otherResourceTypeActions;
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

    #[Ignore]
    public function addAvailableResourceClassAction(AvailableResourceClassAction $availableResourceClassAction): void
    {
        $this->otherResourceTypeActions[] = $availableResourceClassAction;
    }
}
