<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class UserGroupMemberProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly UserGroupService $groupService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof UserGroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGroupMember($item);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof UserGroupMember);

        return $operation === self::REMOVE_ITEM_OPERATION
            && $this->authorizationService->isCurrentUserAuthorizedToRemoveGroupMember($item);
    }

    protected function addItem(mixed $data, array $filters): UserGroupMember
    {
        assert($data instanceof UserGroupMember);

        return $this->groupService->addUserGroupMember($data);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof UserGroupMember);

        $this->groupService->removeUserGroupMember($data);
    }
}
