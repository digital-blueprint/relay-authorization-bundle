<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActions;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<AvailableResourceClassActions>
 *
 * @internal
 */
class AvailableResourceClassActionsProvider extends AbstractDataProvider
{
    private InternalResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService,
        AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->getAvailableResourceClassActions($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $availableResourceClassActions = [];
        foreach ($this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage) as $resourceClass) {
            $availableResourceClassActions[] = $this->getAvailableResourceClassActions($resourceClass);
        }

        return $availableResourceClassActions;
    }

    private function getAvailableResourceClassActions(string $resourceClass): AvailableResourceClassActions
    {
        [$itemActions, $collectionActions] =
            $this->resourceActionGrantService->getAvailableResourceClassActions($resourceClass);

        return new AvailableResourceClassActions($resourceClass, $itemActions, $collectionActions);
    }
}
