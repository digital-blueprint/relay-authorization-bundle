<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
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
            uriTemplate: '/authorization/available-resource-class-actions/{resourceClass}',
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
        'groups' => [
            'AuthorizationAvailableResourceClassActions:output',
        ],
    ],
)]
class AvailableResourceClassActions
{
    #[ApiProperty(identifier: true)]
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private string $resourceClass;

    /**
     * @var array<string, array<string, string>>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'additionalProperties' => [
            'type' => 'string',
        ],
        'example' => [
            'edit' => ['en' => 'Edit', 'de' => 'Editieren'],
            'delete' => ['en' => 'Delete', 'de' => 'Löschen'],
        ],
    ])]
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private array $itemActions;

    /**
     * @var array<string, array<string, string>>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'additionalProperties' => [
            'type' => 'string',
        ],
        'example' => ['create' => ['en' => 'Create', 'de' => 'Erstellen']],
    ])]
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private array $collectionActions;

    /**
     * @param array<string, array<string, string>> $availableResourceItemActions
     * @param array<string, array<string, string>> $availableResourceCollectionActions
     */
    public function __construct(string $resourceClass, array $availableResourceItemActions, array $availableResourceCollectionActions)
    {
        $this->resourceClass = $resourceClass;
        $this->itemActions = $availableResourceItemActions;
        $this->collectionActions = $availableResourceCollectionActions;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getItemActions(): array
    {
        return $this->itemActions;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getCollectionActions(): array
    {
        return $this->collectionActions;
    }
}
