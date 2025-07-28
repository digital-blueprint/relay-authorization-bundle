<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class GetAvailableResourceClassActionsEvent extends Event
{
    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $itemActions = null;

    /**
     * @var array<string, array<string, string>>|null
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
     * @return array<string, array<string, string>>|null
     */
    public function getItemActions(): ?array
    {
        return $this->itemActions;
    }

    /**
     * @param array<string, array<string, string>> $itemActions Example: [
     *                                                          'read' => ['en' => 'Read', 'de' => 'Lesen'],
     *                                                          'write' => ['en' => 'Write', 'de' => 'Schreiben']]
     */
    public function setItemActions(array $itemActions): void
    {
        $this->itemActions = $itemActions;
    }

    /**
     * @return array<string, array<string, string>>|null
     */
    public function getCollectionActions(): ?array
    {
        return $this->collectionActions;
    }

    /**
     * @param array<string, array<string, string>> $collectionActions Examples: [
     *                                                                'create' => ['en' => 'Create', 'de' => 'Erstellen']]
     */
    public function setCollectionActions(array $collectionActions): void
    {
        $this->collectionActions = $collectionActions;
    }
}
