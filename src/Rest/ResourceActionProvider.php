<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\ResouceAction;
use Dbp\Relay\AuthorizationBundle\Service\ResourceActionService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;

/**
 * @extends AbstractDataProvider<ResouceAction>
 */
class ResourceActionProvider extends AbstractDataProvider
{
    private ResourceActionService $resourceActionService;

    public function __construct(ResourceActionService $resourceActionService)
    {
        $this->resourceActionService = $resourceActionService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->resourceActionService->getResourceAction($id, $options);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->resourceActionService->getResourceActions($currentPageNumber, $maxNumItemsPerPage, $options);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        return true;
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
