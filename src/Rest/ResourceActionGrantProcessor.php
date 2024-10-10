<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

/**
 * @internal
 */
class ResourceActionGrantProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly InternalResourceActionGrantService $resourceActionGrantService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * @throws ApiError
     */
    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);
        $this->resourceActionGrantService->ensureAuthorizationResource($item);

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

    /**
     * @throws ApiError
     */
    protected function addItem(mixed $data, array $filters): ResourceActionGrant
    {
        assert($data instanceof ResourceActionGrant);

        return $this->resourceActionGrantService->addResourceActionGrant($data);
    }

    /**
     * @throws ApiError
     */
    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof ResourceActionGrant);

        $this->resourceActionGrantService->removeResourceActionGrant($data);
    }
}
