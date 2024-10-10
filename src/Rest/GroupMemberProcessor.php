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
    public function __construct(
        private readonly GroupService $groupService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof GroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGroupMember($item);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof GroupMember);

        return $this->authorizationService->isCurrentUserAuthorizedToRemoveGroupMember($item);
    }

    protected function addItem(mixed $data, array $filters): GroupMember
    {
        assert($data instanceof GroupMember);

        return $this->groupService->addGroupMember($data);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof GroupMember);

        $this->groupService->removeGroupMember($data);
    }
}
