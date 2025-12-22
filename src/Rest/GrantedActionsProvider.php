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
     * @throws ApiError
     */
    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $resourceClass = $this->getCurrentUriVariables()[Common::RESOURCE_CLASS_URI_VARIABLE_NAME];
        $resourceIdentifier = $this->getCurrentUriVariables()[Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME];

        $grantedActions = new GrantedActions();
        $grantedActions->setResourceClass($resourceClass);
        $grantedActions->setResourceIdentifier($resourceIdentifier);
        $grantedActions->setActions(
            $this->authorizationService->getGrantedResourceActionsForCurrentUser(
                $grantedActions->getResourceClass(), $grantedActions->getResourceIdentifier())
        );

        return $grantedActions;
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $resourceClass = $this->getCurrentUriVariables()[Common::RESOURCE_CLASS_URI_VARIABLE_NAME];

        $grantedActionsPage = [];
        foreach ($this->authorizationService->getGrantedResourceActionsPageForCurrentUser($resourceClass,
            firstResultIndex: Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            maxNumResults: $maxNumItemsPerPage) as $resourceIdentifier => $actions) {
            $grantedActions = new GrantedActions();
            $grantedActions->setResourceClass($resourceClass);
            $grantedActions->setResourceIdentifier($resourceIdentifier);
            $grantedActions->setActions($actions);
            $grantedActionsPage[] = $grantedActions;
        }

        return $grantedActionsPage;
    }
}
