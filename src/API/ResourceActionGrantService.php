<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class ResourceActionGrantService
{
    public const MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;

    private AuthorizationService $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    /**
     * Adds an initial resource manager grant for the given resource for the current user.
     *
     * @throws ApiError
     */
    public function addResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->addResource($resourceClass, $resourceIdentifier);
    }

    /**
     * Deletes all resource action grants for the given resource.
     *
     * @throws ApiError
     */
    public function removeResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->removeResource($resourceClass, $resourceIdentifier);
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceItemActions(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        $internalResourceActionGrants = $this->authorizationService->getResourceItemActionGrants($resourceClass,
            $resourceIdentifier, $actions, $currentPageNumber, $maxNumItemsPerPage);

        $resourceActionGrants = [];
        foreach ($internalResourceActionGrants as $internalResourceActionGrant) {
            $resourceActionGrants[] = new ResourceAction(
                $internalResourceActionGrant->getAuthorizationResource()->getResourceIdentifier(),
                $internalResourceActionGrant->getAction()
            );
        }

        return $resourceActionGrants;
    }

    /**
     * @param array|null $actions null will match any action
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null,
        int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        $internalResourceActionGrants = $this->authorizationService->getResourceCollectionActionGrants(
            $resourceClass, $actions, $currentPageNumber, $maxNumItemsPerPage);

        $resourceActionGrants = [];
        foreach ($internalResourceActionGrants as $internalResourceActionGrant) {
            $resourceActionGrants[] = new ResourceAction(
                null,
                $internalResourceActionGrant->getAction()
            );
        }

        return $resourceActionGrants;
    }
}
