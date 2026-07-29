<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ResourceActionGrantProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService,
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
        $resourceActionGrant = $item;

        $this->ensureAuthorizationResource($resourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToAddGrant($resourceActionGrant);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);
        $resourceActionGrant = $item;

        return match ($operation) {
            self::REMOVE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToRemoveGrant($resourceActionGrant),
            default => false,
        };
    }

    /**
     * @throws ApiError
     */
    protected function addItem(mixed $data, array $filters): ResourceActionGrant
    {
        assert($data instanceof ResourceActionGrant);
        $resourceActionGrant = $data;
        $resourceActionGrant->setCreatorId($this->getUserIdentifier());

        return $this->internalResourceActionGrantService->addResourceActionGrant($resourceActionGrant);
    }

    /**
     * @throws ApiError
     */
    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        assert($data instanceof ResourceActionGrant);
        $resourceActionGrant = $data;

        $this->internalResourceActionGrantService->removeResourceActionGrant($resourceActionGrant);
    }

    /**
     * @throws ApiError
     */
    protected function ensureAuthorizationResource(ResourceActionGrant $resourceActionGrant): void
    {
        if (null === $resourceActionGrant->getResourceClass()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resourceClass is required',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }
        if (null === $resourceActionGrant->getResourceIdentifier()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'resourceIdentifier is required',
                InternalResourceActionGrantService::RESOURCE_ACTION_GRANT_INVALID_ERROR_ID);
        }

        $authorizationResource = $this->internalResourceActionGrantService->getAuthorizationResourceByResourceClassAndIdentifier(
            $resourceActionGrant->getResourceClass(),
            $resourceActionGrant->getResourceIdentifier(),
            $resourceActionGrant->getResourceType()
        );
        if ($authorizationResource === null) {
            $this->internalResourceActionGrantService->throwResourceNotFound();
        }
        $resourceActionGrant->setAuthorizationResource($authorizationResource);
    }
}
