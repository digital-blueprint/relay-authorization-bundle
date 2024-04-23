<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GroupMember;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class GroupMemberProcessor extends AbstractDataProcessor
{
    private GroupService $groupService;
    private AuthorizationService $authorizationService;

    public function __construct(GroupService $groupService, AuthorizationService $authorizationService)
    {
        $this->groupService = $groupService;
        $this->authorizationService = $authorizationService;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof GroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGroupMember($item);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof GroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToRemoveGroupMember($item);
    }

    protected function addItem($data, array $filters)
    {
        assert($data instanceof GroupMember);

        return $this->groupService->addGroupMember($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        assert($data instanceof GroupMember);

        $this->groupService->removeGroupMember($data);
    }
}
