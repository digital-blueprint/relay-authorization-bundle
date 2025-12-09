<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\GrantedActionsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

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
    #[Groups(['AuthorizationGrantedActions:output', 'AuthorizationGrantedActions:input'])]
    private ?string $resourceClass = null;

    #[ApiProperty(identifier: true)]
    #[Groups(['AuthorizationGrantedActions:output', 'AuthorizationGrantedActions:input'])]
    private ?string $resourceIdentifier = null;

    /**
     * @var string[]|null
     */
    #[Groups(['AuthorizationGrantedActions:output'])]
    private ?array $actions = null;

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

    public function getActions(): ?array
    {
        return $this->actions;
    }

    public function setActions(?array $actions): void
    {
        $this->actions = $actions;
    }
}
