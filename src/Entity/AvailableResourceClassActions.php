<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
class AvailableResourceClassActions
{
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private ?string $identifier = null;

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
