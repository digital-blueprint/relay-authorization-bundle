<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<GroupMember>
 *
 * @internal
 */
class GroupMemberProvider extends AbstractDataProvider
{
    public const GROUP_IDENTIFIER_QUERY_PARAMETER = 'groupIdentifier';

    public const GROUP_NOT_FOUND_ERROR_ID = 'authorization:group-not-found';

    private GroupService $groupService;
    private AuthorizationService $authorizationService;

    public function __construct(GroupService $groupService, AuthorizationService $authorizationService)
    {
        $this->groupService = $groupService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->groupService->getGroupMember($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        if (($groupIdentifier = $filters[self::GROUP_IDENTIFIER_QUERY_PARAMETER] ?? null) === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'query parameter \''.self::GROUP_IDENTIFIER_QUERY_PARAMETER.'\' is required',
                Common::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::GROUP_IDENTIFIER_QUERY_PARAMETER]);
        }

        $group = $this->groupService->getGroup($groupIdentifier);
        if ($group === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
                "Group with ID '$groupIdentifier' not found", self::GROUP_NOT_FOUND_ERROR_ID, [$groupIdentifier]);
        }
        if (!$this->authorizationService->isCurrentUserAuthorizedToReadGroup($group)) {
            throw new ApiError(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        return $this->groupService->getGroupMembers(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage, $groupIdentifier);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof GroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGroupMember($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
