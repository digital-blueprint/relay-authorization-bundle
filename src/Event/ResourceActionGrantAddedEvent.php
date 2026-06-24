<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Symfony\Contracts\EventDispatcher\Event;

class ResourceActionGrantAddedEvent extends Event
{
    public function __construct(private readonly ResourceActionGrant $resourceActionGrant)
    {
    }

    public function getResourceActionGrant(): ResourceActionGrant
    {
        return $this->resourceActionGrant;
    }
}
