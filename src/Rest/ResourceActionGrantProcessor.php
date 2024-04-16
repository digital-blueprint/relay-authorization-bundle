<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

class ResourceActionGrantProcessor extends AbstractDataProcessor
{
    private ResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(ResourceActionGrantService $resourceActionService, AuthorizationService $authorizationService)
    {
        $this->resourceActionGrantService = $resourceActionService;
        $this->authorizationService = $authorizationService;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGrant($item);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        switch ($operation) {
            case self::REMOVE_ITEM_OPERATION:
                return $this->authorizationService->isCurrentUserAuthorizedToRemoveGrant($item);
        }

        return false;
    }

    protected function addItem($data, array $filters)
    {
        assert($data instanceof ResourceActionGrant);

        return $this->resourceActionGrantService->addResourceActionGrant($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        assert($data instanceof ResourceActionGrant);

        $this->resourceActionGrantService->removeResourceActionGrant($data);
    }
}
