<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<UserGroupMember>
 *
 * @internal
 */
class UserGroupMemberProvider extends AbstractDataProvider
{
    public const GROUP_IDENTIFIER_QUERY_PARAMETER = 'userGroupIdentifier';

    public const GROUP_NOT_FOUND_ERROR_ID = 'authorization:user-group-not-found';

    public function __construct(
        private readonly UserGroupService $groupService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->groupService->getUserGroupMember($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        if (($groupIdentifier = $filters[self::GROUP_IDENTIFIER_QUERY_PARAMETER] ?? null) === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'query parameter \''.self::GROUP_IDENTIFIER_QUERY_PARAMETER.'\' is required',
                Common::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::GROUP_IDENTIFIER_QUERY_PARAMETER]);
        }

        $userGroup = $this->groupService->tryGetUserGroup($groupIdentifier);
        if ($userGroup === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
                "Group with ID '$groupIdentifier' not found", self::GROUP_NOT_FOUND_ERROR_ID, [$groupIdentifier]);
        }
        if (!$this->authorizationService->isCurrentUserAuthorizedToReadGroup($userGroup)) {
            throw new ApiError(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        return $this->groupService->getUserGroupMembers(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage, $groupIdentifier);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof UserGroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToReadUserGroupMember($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
