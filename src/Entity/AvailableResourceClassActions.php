<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
class AvailableResourceClassActions
{
    private string $identifier = 'available-resource-class-actions';

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

    public function __construct(?array $availableResourceItemActions, ?array $availableResourceCollectionActions)
    {
        $this->itemActions = $availableResourceItemActions;
        $this->collectionActions = $availableResourceCollectionActions;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
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
