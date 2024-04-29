<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<GroupMember>
 *
 * @internal
 */
class GroupMemberProvider extends AbstractDataProvider
{
    public const GROUP_IDENTIFIER_QUERY_PARAMETER = 'groupIdentifier';

    public const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'authorization:required-parameter-missing';

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
                self::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::GROUP_IDENTIFIER_QUERY_PARAMETER]);
        }

        return $this->groupService->getGroupMembers($currentPageNumber, $maxNumItemsPerPage, $groupIdentifier);
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
