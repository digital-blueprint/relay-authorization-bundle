<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class GroupProcessor extends AbstractDataProcessor
{
    private GroupService $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function addItem($data, array $filters)
    {
        assert($data instanceof Group);

        return $this->groupService->addGroup($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        assert($data instanceof Group);

        $this->groupService->removeGroup($data);
    }
}
