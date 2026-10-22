<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class UserGroupMemberProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function addItem(mixed $data, array $filters): UserGroupMember
    {
        assert($data instanceof UserGroupMember);

        return $this->authorizationService->addUserGroupMemberIfGranted($data);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof UserGroupMember);

        $this->authorizationService->removeUserGroupMemberIfGranted($data);
    }
}
