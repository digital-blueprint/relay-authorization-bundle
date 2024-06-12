<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

class ResourceActions
{
    private ?string $resourceIdentifier;

    /**
     * @var string[]
     */
    private array $actions;

    public function __construct(?string $resourceIdentifier, array $actions = [])
    {
        $this->resourceIdentifier = $resourceIdentifier;
        $this->actions = $actions;
    }

    /**
     * @return string|null if null the action represents a collection action
     */
    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    /**
     * @return string[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function addAction(string $action): void
    {
        if (!in_array($action, $this->actions, true)) {
            $this->actions[] = $action;
        }
    }
}
