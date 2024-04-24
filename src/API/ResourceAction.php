<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

class ResourceAction
{
    private ?string $resourceIdentifier;
    private string $action;

    public function __construct(?string $resourceIdentifier, string $action)
    {
        $this->resourceIdentifier = $resourceIdentifier;
        $this->action = $action;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
