<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class ResourceActionGrantService
{
    public const MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;
    public const MAX_NUM_RESULTS_DEFAULT = 30;
    public const MAX_NUM_RESULTS_MAX = 1024;

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
     * Deletes all resource action grants for the given resources.
     *
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeResources(string $resourceClass, array $resourceIdentifiers): void
    {
        if (!empty($resourceIdentifiers)) {
            $this->authorizationService->removeResources($resourceClass, $resourceIdentifiers);
        }
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasUserGrantedResourceItemActions(string $userIdentifier, string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null): bool
    {
        return count($this->authorizationService->getResourceItemActionGrantsForUser($userIdentifier, $resourceClass,
            $resourceIdentifier, $actions, 0, 1)) > 0;
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasGrantedResourceItemActions(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null): bool
    {
        return count($this->authorizationService->getResourceItemActionGrantsForCurrentUser($resourceClass,
            $resourceIdentifier, $actions, 0, 1)) > 0;
    }

    /**
     * @parram string|null $resourceIdentifier null matches any resource identifier
     *
     * @param array|null $actions null matches any action
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceItemActions(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $maxNumResults = min($maxNumResults, self::MAX_NUM_RESULTS_MAX);

        $internalResourceActionGrants = $this->authorizationService->getResourceItemActionGrantsForCurrentUser($resourceClass,
            $resourceIdentifier, $actions, $firstResultIndex, $maxNumResults);

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
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null): bool
    {
        return count($this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            $resourceClass, $actions, 0, 1)) > 0;
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        $maxNumResults = min($maxNumResults, self::MAX_NUM_RESULTS_MAX);

        $internalResourceActionGrants = $this->authorizationService->getResourceCollectionActionGrantsForCurrentUser(
            $resourceClass, $actions, $firstResultIndex, $maxNumResults);

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
