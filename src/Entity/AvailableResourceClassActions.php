<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationAvailableResourceClassActions',
    operations: [
        new Get(
            uriTemplate: '/authorization/available-resource-class-actions/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: AvailableResourceClassActionsProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/available-resource-class-actions',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: AvailableResourceClassActionsProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationAvailableResourceClassActions:output'],
    ],
)]
class AvailableResourceClassActions
{
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private ?string $identifier;

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private ?array $itemActions;

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private ?array $collectionActions;

    public function __construct(string $resourceClass, ?array $availableResourceItemActions, ?array $availableResourceCollectionActions)
    {
        $this->identifier = $resourceClass;
        $this->itemActions = $availableResourceItemActions;
        $this->collectionActions = $availableResourceCollectionActions;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string[]|null
     */
    public function getItemActions(): ?array
    {
        return $this->itemActions;
    }

    /**
     * @return string[]|null
     */
    public function getCollectionActions(): ?array
    {
        return $this->collectionActions;
    }
}
