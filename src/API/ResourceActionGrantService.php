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
        readonly AuthorizationService $authorizationService)
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
    public function registerResource(string $resourceClass, string $resourceIdentifier, ?string $userIdentifier = null): void
    {
        $this->authorizationService->registerResource($resourceClass, $resourceIdentifier, $userIdentifier);
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
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier, string $action,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): void
    {
        $this->authorizationService->addResourceActionGrant($resourceClass, $resourceIdentifier, $action,
            $userIdentifier, $groupIdentifier, $dynamicGroupIdentifier);
    }

    /**
     * @param string[] $anyOfItemActions Only return true if the granted item actions contain any of the given actions
     *
     * @throws ApiError
     */
    public function isCurrentUserGrantedAnyOfItemActions(string $resourceClass, string $resourceIdentifier,
        array $anyOfItemActions): bool
    {
        return !empty($this->authorizationService->getResourceItemActionsForCurrentUser($resourceClass,
            $resourceIdentifier, $anyOfItemActions));
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
     * @param string[]|null $anyOfITemActions Only return item actions of resources that contain any of the given item actions
     *
     * @return string[][]
     *
     * @throws ApiError
     */
    public function getGrantedItemActionsPageForCurrentUser(string $resourceClass,
        ?array $anyOfITemActions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getResourceItemActionsPageForCurrentUser(
            $resourceClass, $anyOfITemActions, $firstResultIndex, min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }

    /**
     * @param string[] $anyOfCollectionActions Only return true if the granted collection actions contain any of the given actions
     *
     * @throws ApiError
     */
    public function isCurrentUserGrantedAnyOfCollectionActions(string $resourceClass, array $anyOfCollectionActions): bool
    {
        return !empty($this->authorizationService->getResourceCollectionActionsForCurrentUser(
            $resourceClass, $anyOfCollectionActions));
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
