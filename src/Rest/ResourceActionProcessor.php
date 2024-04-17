<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceAction;
use Dbp\Relay\AuthorizationBundle\Service\ResourceActionService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
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

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        return true;
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        return true;
    }

    protected function addItem($data, array $filters)
    {
        assert($data instanceof ResourceAction);

        return $this->resourceActionService->addResourceAction($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        $this->resourceActionService->removeResourceAction($data);
    }
}
