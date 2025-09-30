<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;

class ResourceActionGrantService
{
    public const MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;
    public const MAX_NUM_RESULTS_DEFAULT = 30;
    public const MAX_NUM_RESULTS_MAX = 1024;

    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
    }

    /**
     * @internal
     *
     * For testing only
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->authorizationService->setEntityManager($entityManager);
    }

    /**
     * @internal
     *
     * For testing only
     */
    public function getAuthorizationService(): AuthorizationService
    {
        return $this->authorizationService;
    }

    /**
     * Adds an initial resource manager grant for the given resource for the current user.
     *
     * @param ?string $userIdentifier The user identifier of the resource manager. If not provided, the currently logged-in user is used.
     *
     * @throws ApiError
     */
    public function registerResource(string $resourceClass, string $resourceIdentifier, ?string $userIdentifier = null,
        bool $addManageGrant = true): void
    {
        $this->authorizationService->registerResource($resourceClass, $resourceIdentifier, $userIdentifier, $addManageGrant);
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
            $this->authorizationService->deregisterResources($resourceClass, $resourceIdentifiers);
        }
    }

    /**
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, ?string $resourceIdentifier, string $action,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): void
    {
        $this->authorizationService->addResourceActionGrant($resourceClass, $resourceIdentifier, $action,
            $userIdentifier, $groupIdentifier, $dynamicGroupIdentifier);
    }

    /**
     * Parameters with null values will not be filtered on.
     * NOTE: The grant holder criteria (userIdentifier, groupIdentifiers, dynamicGroupIdentifiers) is logically combined
     * with an OR conjunction.
     *
     * @param ?string[] $actions
     *
     * @throws ApiError
     */
    public function removeResourceActionGrants(string $resourceClass, ?string $resourceIdentifier, ?array $actions = null,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): void
    {
        $this->authorizationService->removeResourceActionGrants($resourceClass, $resourceIdentifier, $actions,
            $userIdentifier, $groupIdentifier, $dynamicGroupIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserGrantedItemAction(string $resourceClass, string $resourceIdentifier,
        string $itemAction): bool
    {
        return $this->authorizationService->isCurrentUserGranted($resourceClass, $resourceIdentifier, $itemAction);
    }

    /**
     * @return string[]
     *
     * @throws ApiError
     */
    public function getGrantedItemActionsForCurrentUser(string $resourceClass, string $resourceIdentifier): array
    {
        return $this->authorizationService->getResourceItemActionsForCurrentUser($resourceClass, $resourceIdentifier);
    }

    /**
     * @return string[][]
     *
     * @throws ApiError
     */
    public function getGrantedItemActionsPageForCurrentUser(string $resourceClass,
        ?string $whereIsGrantedAction = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            $resourceClass, $whereIsGrantedAction, $firstResultIndex, min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }

    /**
     * @throws ApiError
     */
    public function isCurrentUserGrantedCollectionAction(string $resourceClass, string $collectionAction): bool
    {
        return $this->authorizationService->isCurrentUserGranted($resourceClass, null, $collectionAction);
    }

    /**
     * @return string[]
     *
     * @throws ApiError
     */
    public function getGrantedCollectionActionsForCurrentUser(string $resourceClass): array
    {
        return $this->authorizationService->getResourceCollectionActionsForCurrentUser(
            $resourceClass);
    }
}
