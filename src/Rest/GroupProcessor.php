<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Group;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * @internal
 */
class GroupProcessor extends AbstractDataProcessor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly GroupService $groupService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof Group);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGroups();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof Group);

        return match ($operation) {
            self::UPDATE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($item),
            self::REMOVE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($item),
            default => false,
        };
    }

    protected function addItem(mixed $data, array $filters): Group
    {
        assert($data instanceof Group);
        $group = $data;

        $group = $this->groupService->addGroup($group);

        try {
            $this->authorizationService->addGroup($group->getIdentifier());
        } catch (\Exception $e) {
            // remove inaccessible group
            $this->groupService->removeGroup($group);
            throw $e;
        }

        return $group;
    }

    protected function updateItem(mixed $identifier, mixed $data, $previousData, array $filters): Group
    {
        assert($data instanceof Group);
        $group = $data;

        return $this->groupService->updateGroup($group);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof Group);
        $group = $data;

        $this->groupService->removeGroup($group);

        try {
            $this->authorizationService->removeGroup($group->getIdentifier());
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Failed to remove group resource \'%s\' from authorization: %s', $group->getIdentifier(), $e->getMessage()));
        }
    }
}
