<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\DynamicGroup;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<DynamicGroup>
 *
 * @internal
 */
class DynamicGroupProvider extends AbstractDataProvider
{
    private AuthorizationService $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $dynamicGroup = null;
        if (in_array($id, $this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead(), true)) {
            $dynamicGroup = new DynamicGroup($id);
        }

        return $dynamicGroup;
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $dynamicGroups = [];
        foreach (array_slice($this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead(),
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage) as $dynamicGroup) {
            $dynamicGroups[] = new DynamicGroup($dynamicGroup);
        }

        return $dynamicGroups;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }
}
