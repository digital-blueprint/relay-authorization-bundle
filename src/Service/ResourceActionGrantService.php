<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class ResourceActionGrantService
{
    public const MANAGE_ACTION = InternalResourceActionGrantService::MANAGE_ACTION;
    public const IS_NULL = InternalResourceActionGrantService::IS_NULL;
    public const IS_NOT_NULL = InternalResourceActionGrantService::IS_NOT_NULL;

    private InternalResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService, AuthorizationService $authorizationService)
    {
        $this->resourceActionGrantService = $resourceActionGrantService;
        $this->authorizationService = $authorizationService;
    }

    /**
     * Adds an initial resource manager grant for the given resource for the current user.
     *
     * @throws ApiError
     */
    public function addResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->resourceActionGrantService->addResourceAndManageResourceGrantForUser(
            $resourceClass, $resourceIdentifier, $this->authorizationService->getUserIdentifier());
    }

    /**
     * Deletes all resource action grants for the given resource.
     *
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->resourceActionGrantService->removeResource($resourceClass, $resourceIdentifier);
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrants(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, ?string $userIdentifier = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        return $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $actions, $userIdentifier, $currentPageNumber, $maxNumItemsPerPage);
    }
}
