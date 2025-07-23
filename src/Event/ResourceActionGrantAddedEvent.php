<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ResourceActionGrantAddedEvent extends Event
{
    public function __construct(
        private readonly string $resourceClass,
        private readonly ?string $resourceIdentifier,
        private readonly string $action,
        private readonly ?string $userIdentifier,
        private readonly ?string $groupIdentifier,
        private readonly ?string $dynamicGroupIdentifier)
    {
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getGroupIdentifier(): ?string
    {
        return $this->groupIdentifier;
    }

    public function getDynamicGroupIdentifier(): ?string
    {
        return $this->dynamicGroupIdentifier;
    }
}
