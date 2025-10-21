<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
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
     * Deletes all resource action grants for the given resource.
     *
     * @throws ApiError
     */
    public function removeGrantsForResource(string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->removeGrantsForResource($resourceClass, $resourceIdentifier);
    }

    /**
     * Deletes all resource action grants for the given resources.
     *
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeGrantsForResources(string $resourceClass, array $resourceIdentifiers): void
    {
        if (!empty($resourceIdentifiers)) {
            $this->authorizationService->removeGrantsForResources($resourceClass, $resourceIdentifiers);
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
     * @throws ApiError
     */
    public function removeResourceActionGrant(string $identifier): void
    {
        $this->authorizationService->removeResourceActionGrant($identifier);
    }

    /**
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, ?string $resourceIdentifier): array
    {
        return $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function addGrantInheritance(string $sourceResourceClass, ?string $sourceResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): void
    {
        $this->authorizationService->addGrantInheritance(
            $sourceResourceClass, $sourceResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeGrantInheritance(string $sourceResourceClass, ?string $sourceResourceIdentifier,
        string $targetResourceClass, ?string $targetResourceIdentifier): void
    {
        $this->authorizationService->removeGrantInheritance(
            $sourceResourceClass, $sourceResourceIdentifier,
            $targetResourceClass, $targetResourceIdentifier);
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
