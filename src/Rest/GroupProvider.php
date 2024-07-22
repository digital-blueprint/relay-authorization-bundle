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
    private GroupService $groupService;
    private AuthorizationService $authorizationService;

    public function __construct(GroupService $groupService, AuthorizationService $authorizationService)
    {
        $this->groupService = $groupService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->groupService->tryGetGroup($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->authorizationService->getGroupsCurrentUserIsAuthorizedToRead(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage, $filters);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof Group);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGroup($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
