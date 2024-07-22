<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class ResourceActionGrantProcessor extends AbstractDataProcessor
{
    private InternalResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(InternalResourceActionGrantService $resourceActionService, AuthorizationService $authorizationService)
    {
        $this->resourceActionGrantService = $resourceActionService;
        $this->authorizationService = $authorizationService;
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGrant($item);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return match ($operation) {
            self::REMOVE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToRemoveGrant($item),
            default => false,
        };
    }

    protected function addItem(mixed $data, array $filters): ResourceActionGrant
    {
        assert($data instanceof ResourceActionGrant);

        return $this->resourceActionGrantService->addResourceActionGrant($data);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof ResourceActionGrant);

        $this->resourceActionGrantService->removeResourceActionGrant($data);
    }
}
