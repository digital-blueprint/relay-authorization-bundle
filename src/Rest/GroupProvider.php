<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<Group>
 *
 * @internal
 */
class GroupProvider extends AbstractDataProvider
{
    private const SEARCH_QUERY_PARAMETER = GroupService::SEARCH_FILTER_OPTION;

    private GroupService $groupService;
    private AuthorizationService $authorizationService;

    public function __construct(GroupService $groupService, AuthorizationService $authorizationService)
    {
        $this->groupService = $groupService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->groupService->getGroup($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $options = [];
        if ($searchParameter = $filters[self::SEARCH_QUERY_PARAMETER] ?? null) {
            $options[GroupService::SEARCH_FILTER_OPTION] = $searchParameter;
        }

        return $this->authorizationService->getGroupsCurrentUserIsAuthorizedToRead(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage, $options);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof Group);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGroup($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
