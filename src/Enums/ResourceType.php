<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Enums;

enum ResourceType: int
{
    case RESOURCE = 0;
    case RESOURCE_GROUP = 1;
    case DYNAMIC_RESOURCE_GROUP = 2;
}
