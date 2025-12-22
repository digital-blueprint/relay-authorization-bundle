<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class ResourceActionGrantService
{
    public const COLLECTION_RESOURCE_IDENTIFIER = AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;
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
    public function getAuthorizationService(): AuthorizationService
    {
        return $this->authorizationService;
    }

    /**
     * @param array<string, array<string, string>> $itemActions       A mapping from item action names to their localized names
     * @param array<string, array<string, string>> $collectionActions A mapping from collection action names to their localized names
     */
    public function setAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): void
    {
        $this->authorizationService->setAvailableResourceClassActions(
            $resourceClass, $itemActions, $collectionActions);
    }

    /**
     * Deletes all resource action grants for the given resource.
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
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
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier, string $action,
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
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @param bool $ignoreActionAvailability if true, grants are returned if the granted action is not available for the resource class
     *
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier, bool $ignoreActionAvailability = false): array
    {
        return $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $ignoreActionAvailability);
    }

    /**
     * @throws ApiError
     */
    public function addResourceToGroupResource(string $groupResourceClass, string $groupResourceIdentifier,
        string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->addResourceToGroupResource(
            $groupResourceClass, $groupResourceIdentifier,
            $resourceClass, $resourceIdentifier);
    }

    /**
     * @throws ApiError
     */
    public function removeResourceFromGroupResource(string $groupResourceClass, string $groupResourceIdentifier,
        string $resourceClass, string $resourceIdentifier): void
    {
        $this->authorizationService->removeResourceFromGroupResource(
            $groupResourceClass, $groupResourceIdentifier,
            $resourceClass, $resourceIdentifier);
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function isCurrentUserGranted(string $resourceClass, string $resourceIdentifier,
        string $action): bool
    {
        return $this->authorizationService->isCurrentUserGranted($resourceClass, $resourceIdentifier, $action);
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @return string[]
     *
     * @throws ApiError
     */
    public function getGrantedActionsForCurrentUser(string $resourceClass, string $resourceIdentifier): array
    {
        return $this->authorizationService->getGrantedResourceActionsForCurrentUser($resourceClass, $resourceIdentifier);
    }

    /**
     * @return string[][]
     *
     * @throws ApiError
     */
    public function getGrantedActionsPageForCurrentUser(string $resourceClass,
        ?string $whereIsGrantedAction = null,
        bool $excludeCollectionResource = true,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getGrantedResourceActionsPageForCurrentUser(
            $resourceClass, $whereIsGrantedAction, $excludeCollectionResource,
            $firstResultIndex, min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }
}
