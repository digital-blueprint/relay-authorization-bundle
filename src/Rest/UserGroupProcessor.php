<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * @internal
 */
class UserGroupProcessor extends AbstractDataProcessor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly UserGroupService $groupService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof UserGroup);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGroups();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof UserGroup);

        return match ($operation) {
            self::UPDATE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToUpdateGroup($item),
            self::REMOVE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToRemoveGroup($item),
            default => false,
        };
    }

    protected function addItem(mixed $data, array $filters): UserGroup
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        $userGroup = $this->groupService->addUserGroup($userGroup);

        try {
            $this->authorizationService->addUserGroup($userGroup->getIdentifier());
        } catch (\Exception $e) {
            // remove inaccessible user group
            $this->groupService->removeUserGroup($userGroup);
            throw $e;
        }

        return $userGroup;
    }

    protected function updateItem(mixed $identifier, mixed $data, $previousData, array $filters): UserGroup
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        return $this->groupService->updateUserGroup($userGroup);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        $this->groupService->removeUserGroup($userGroup);

        try {
            $this->authorizationService->removeUserGroup($userGroup->getIdentifier());
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Failed to remove group resource \'%s\' from authorization: %s', $userGroup->getIdentifier(), $e->getMessage()));
        }
    }
}
