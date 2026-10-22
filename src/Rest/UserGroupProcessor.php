<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
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
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * @throws \Throwable
     */
    protected function addItem(mixed $data, array $filters): UserGroup
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        return $this->authorizationService->addUserGroupIfGranted($userGroup);
    }

    /**
     * @throws \Throwable
     */
    protected function updateItem(mixed $identifier, mixed $data, $previousData, array $filters): UserGroup
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        return $this->authorizationService->updateUserGroupIfGranted($userGroup);
    }

    /**
     * @throws \Throwable
     */
    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof UserGroup);
        $userGroup = $data;

        $this->authorizationService->removeUserGroupIfGranted($userGroup);
    }
}
