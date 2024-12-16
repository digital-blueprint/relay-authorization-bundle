<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class GetAvailableResourceClassActionsEvent extends Event
{
    /**
     * @var string[]|null
     */
    private ?array $itemActions = null;

    /**
     * @var string[]|null
     */
    private ?array $collectionActions = null;

    public function __construct(private readonly string $resourceClass)
    {
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return string[]|null
     */
    public function getItemActions(): ?array
    {
        return $this->itemActions;
    }

    /**
     * @param string[] $itemActions
     */
    public function setItemActions(array $itemActions): void
    {
        $this->itemActions = $itemActions;
    }

    /**
     * @return string[]|null
     */
    public function getCollectionActions(): ?array
    {
        return $this->collectionActions;
    }

    /**
     * @param string[] $collectionActions
     */
    public function setCollectionActions(array $collectionActions): void
    {
        $this->collectionActions = $collectionActions;
    }
}
