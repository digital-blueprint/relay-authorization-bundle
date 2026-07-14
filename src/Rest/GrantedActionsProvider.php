<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<GrantedActions>
 *
 * @internal
 */
class GrantedActionsProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * @retrun GrantedActions|null
     *
     * @throws ApiError
     */
    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        throw new \RuntimeException('Get item operation not available');
    }

    /**
     * @return GrantedActions[]
     *
     * @throws ApiError
     */
    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $resourceClass = Common::getResourceClassFilter($filters);
        $resourceIdentifier = Common::getResourceIdentifierFilter($filters);
        $resourceType = Common::getResourceTypeFilter($filters);

        if (null !== $resourceClass && null !== $resourceIdentifier) {
            return ($grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser($resourceClass, $resourceIdentifier, $resourceType)) ?
                [$grantedActions] :
                [];
        }

        return $this->authorizationService->getGrantedActionsCollectionForCurrentUser($resourceClass,
            firstResultIndex: Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            maxNumResults: $maxNumItemsPerPage
        );
    }
}
