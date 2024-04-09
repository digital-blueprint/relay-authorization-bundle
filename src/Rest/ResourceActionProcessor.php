<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Service\ResourceActionService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

class ResourceActionProcessor extends AbstractDataProcessor
{
    private ResourceActionService $resourceActionService;

    public function __construct(ResourceActionService $resourceActionService)
    {
        $this->resourceActionService = $resourceActionService;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        return true;
    }
}
