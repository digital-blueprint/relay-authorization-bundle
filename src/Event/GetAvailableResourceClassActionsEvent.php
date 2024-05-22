<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class GetAvailableResourceClassActionsEvent extends Event
{
    private string $resourceClass;

    /**
     * @var string[]
     */
    private array $availableResourceItemActions = [];

    /**
     * @var string[]
     */
    private array $availableResourceCollectionActions = [];

    public function __construct(string $resourceClass)
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return string[]
     */
    public function getAvailableResourceItemActions(): array
    {
        return $this->availableResourceItemActions;
    }

    /**
     * @param string[] $availableResourceItemActions
     */
    public function setAvailableResourceItemActions(array $availableResourceItemActions): void
    {
        $this->availableResourceItemActions = $availableResourceItemActions;
    }

    /**
     * @return string[]
     */
    public function getAvailableResourceCollectionActions(): array
    {
        return $this->availableResourceCollectionActions;
    }

    /**
     * @param string[] $availableResourceCollectionActions
     */
    public function setAvailableResourceCollectionActions(array $availableResourceCollectionActions): void
    {
        $this->availableResourceCollectionActions = $availableResourceCollectionActions;
    }
}
