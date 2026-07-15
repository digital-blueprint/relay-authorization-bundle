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
        $resourceClass = Common::getResourceClassQueryParameter($filters);
        $resourceIdentifier = Common::getResourceIdentifierQueryParameter($filters);
        $resourceType = Common::getResourceTypeQueryParameter($filters);
        $excludeCollectionResources = Common::getExcludeCollectionResourcesFilter($filters);
        $whereIsGrantedAction = Common::getWhereIsGrantedActionFilter($filters);

        if (null !== $resourceClass
            && null !== $resourceIdentifier
            && null !== $resourceType
            && null === $whereIsGrantedAction
            && (false === $excludeCollectionResources
                || AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER !== $resourceIdentifier)) {
            return ($grantedActions = $this->authorizationService->getGrantedActionsForCurrentUser($resourceClass, $resourceIdentifier, $resourceType)) ?
                [$grantedActions] :
                [];
        }

        return $this->authorizationService->getGrantedActionsCollectionForCurrentUser($resourceClass,
            whereIsGrantedAction: $whereIsGrantedAction,
            excludeCollectionResources: $excludeCollectionResources,
            firstResultIndex: Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            maxNumResults: $maxNumItemsPerPage
        );
    }
}
