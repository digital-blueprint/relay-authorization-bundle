<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;

class ResourceActionGrantService
{
    public const COLLECTION_RESOURCE_IDENTIFIER = AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;

    public const MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;
    public const MANAGER_ROLE_IDENTIFIER = AuthorizationService::MANAGER_ROLE_IDENTIFIER;

    public const ITEM_ACTION_TYPE = AvailableResourceClassAction::ITEM_ACTION_TYPE;
    public const COLLECTION_ACTION_TYPE = AvailableResourceClassAction::COLLECTION_ACTION_TYPE;

    public const RESOURCE_RESOURCE_TYPE = AuthorizationService::RESOURCE_RESOURCE_TYPE;
    public const RESOURCE_GROUP_RESOURCE_TYPE = AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;

    public const MAX_NUM_RESULTS_DEFAULT = 30;
    public const MAX_NUM_RESULTS_MAX = 1024;

    public static function createRoleAction(
        ?string $resourceClass,
        string $action,
        int $actionType = AvailableResourceClassAction::ITEM_ACTION_TYPE): array
    {
        return [
            'resourceClass' => $resourceClass,
            'action' => $action,
            'actionType' => $actionType,
        ];
    }

    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->authorizationService->getEntityManager();
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
    public function addOrUpdateAvailableResourceClassActions(string $resourceClass,
        array $itemActions, array $collectionActions): array
    {
        return $this->authorizationService->addOrUpdateAvailableResourceClassActions(
            $resourceClass, $itemActions, $collectionActions);
    }

    public function addOrUpdateRole(array $localizedRoleNames, array $roleActions, ?string $identifier = null): Role
    {
        return $this->authorizationService->addOrUpdateRole($localizedRoleNames, $roleActions, $identifier);
    }

    /**
     * Deletes all resource action grants for the given resource.
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function removeGrantsForResource(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->authorizationService->removeGrantsForResource($resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function removeResource(
        ?string $resourceClass = null, ?string $resourceIdentifier = null, ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->authorizationService->removeResource($resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeResources(
        ?string $resourceClass = null, array $resourceIdentifiers = [], ?int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        if (!empty($resourceIdentifiers)) {
            $this->authorizationService->removeResources($resourceClass, $resourceIdentifiers, $resourceType);
        }
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $action = null, ?string $roleIdentifier = null,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): ResourceActionGrant
    {
        return $this->authorizationService->addResourceActionGrant(
            $resourceClass, $resourceIdentifier, $resourceType,
            $action, $roleIdentifier,
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
     * @return ResourceActionGrant[]
     *
     * @throws ApiError
     */
    public function getResourceActionGrantsForResourceClassAndIdentifier(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): array
    {
        return $this->authorizationService->getResourceActionGrantsForResourceClassAndIdentifier(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function addResourceToGroupResource(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->authorizationService->addResourceToGroupResource(
            $resourceClass, $resourceGroupResourceIdentifier,
            $resourceIdentifier, $resourceType);
    }

    /**
     * @throws ApiError
     */
    public function removeResourceFromGroupResource(string $resourceClass, string $resourceGroupResourceIdentifier,
        string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->authorizationService->removeResourceFromGroupResource(
            $resourceClass, $resourceGroupResourceIdentifier,
            $resourceIdentifier, $resourceType);
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function isCurrentUserGranted(string $resourceClass, string $resourceIdentifier,
        string $action, int $resourceType = self::RESOURCE_RESOURCE_TYPE): bool
    {
        return $this->authorizationService->isCurrentUserGranted(
            $resourceClass, $resourceIdentifier, $action, $resourceType);
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     * Returns null, if the current user is not granted any actions for the given resource.
     *
     * @throws ApiError
     */
    public function getGrantedActionsForCurrentUser(string $resourceClass, string $resourceIdentifier,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE): ?GrantedActions
    {
        return $this->authorizationService->getGrantedActionsForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * Only includes resources where the current user is granted at least one action.
     *
     * @return GrantedActions[]
     *
     * @throws ApiError
     */
    public function getGrantedActionsCollectionForCurrentUser(
        ?string $resourceClass = null,
        ?string $resourceIdentifier = null,
        ?int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $whereIsGrantedAction = null,
        bool $excludeCollectionResource = true,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getGrantedActionsCollectionForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType,
            whereIsGrantedAction: $whereIsGrantedAction,
            excludeCollectionResources: $excludeCollectionResource,
            firstResultIndex: $firstResultIndex,
            maxNumResults: min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }
}
