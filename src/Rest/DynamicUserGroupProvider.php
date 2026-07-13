<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\DynamicUserGroup;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<DynamicUserGroup>
 *
 * @internal
 */
class DynamicUserGroupProvider extends AbstractDataProvider
{
    public function __construct(private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        throw new \RuntimeException('Item operation is not available for this resource');
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $dynamicGroups = [];
        foreach (array_slice($this->authorizationService->getDynamicGroupsCurrentUserIsAuthorizedToRead(),
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage) as $dynamicGroup) {
            $dynamicGroups[] = new DynamicUserGroup($dynamicGroup);
        }

        return $dynamicGroups;
    }
}
