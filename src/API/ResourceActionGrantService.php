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
    public function registerResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->registerResource($resourceClass, $resourceIdentifier);
    }

    /**
     * Deletes all resource action grants for the given resource.
     *
     * @throws ApiError
     */
    public function deregisterResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->deregisterResource($resourceClass, $resourceIdentifier);
    }

    /**
     * Deletes all resource action grants for the given resources.
     *
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function deregisterResources(string $resourceClass, array $resourceIdentifiers): void
    {
        if (!empty($resourceIdentifiers)) {
            $this->authorizationService->removeResources($resourceClass, $resourceIdentifiers);
        }
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasUserGrantedResourceItemActions(string $userIdentifier, string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): bool
    {
        return $this->authorizationService->getResourceItemActionsForUser($userIdentifier, $resourceClass,
            $resourceIdentifier, $actions) !== null;
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasGrantedResourceItemActions(string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): bool
    {
        return $this->authorizationService->getResourceItemActionsForCurrentUser($resourceClass,
            $resourceIdentifier, $actions) !== null;
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function getGrantedResourceItemActions(string $resourceClass, string $resourceIdentifier,
        ?array $actions = null): ?ResourceActions
    {
        return $this->authorizationService->getResourceItemActionsForCurrentUser($resourceClass,
            $resourceIdentifier, $actions);
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @return ResourceActions[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceItemActionsPage(string $resourceClass,
        ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            $resourceClass, $actions, $firstResultIndex, min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function hasGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null): bool
    {
        return $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            $resourceClass, $actions) !== null;
    }

    /**
     * @param array|null $actions null matches any action
     *
     * @throws ApiError
     */
    public function getGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null): ?ResourceActions
    {
        return $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            $resourceClass, $actions);
    }
}
