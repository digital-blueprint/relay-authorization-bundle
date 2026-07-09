<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class ResourceActionGrantService
{
    public const COLLECTION_RESOURCE_IDENTIFIER = AuthorizationService::COLLECTION_RESOURCE_IDENTIFIER;

    public const MANAGE_ACTION = AuthorizationService::MANAGE_ACTION;

    public const ITEM_ACTION_TYPE = AvailableResourceClassAction::ITEM_ACTION_TYPE;
    public const COLLECTION_ACTION_TYPE = AvailableResourceClassAction::COLLECTION_ACTION_TYPE;

    public const RESOURCE_RESOURCE_TYPE = AuthorizationService::RESOURCE_RESOURCE_TYPE;
    public const RESOURCE_GROUP_RESOURCE_TYPE = AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE;

    public const MAX_NUM_RESULTS_DEFAULT = 30;
    public const MAX_NUM_RESULTS_MAX = 1024;

    public static function createRoleAction(string $resourceClass, string $action, int $actionType): array
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

    public function addRole(array $localizedRoleNames, array $roleActions): Role
    {
        return $this->authorizationService->addRole($localizedRoleNames, $roleActions);
    }

    /**
     * Deletes all resource action grants for the given resource.
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function removeGrantsForResource(
        string $resourceClass, string $resourceIdentifier, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        $this->authorizationService->removeGrantsForResource($resourceClass, $resourceIdentifier, $resourceType);
    }

    /**
     * Deletes all resource action grants for the given resources.
     *
     * @param string[] $resourceIdentifiers
     *
     * @throws ApiError
     */
    public function removeGrantsForResources(
        string $resourceClass, array $resourceIdentifiers, int $resourceType = self::RESOURCE_RESOURCE_TYPE): void
    {
        if (!empty($resourceIdentifiers)) {
            $this->authorizationService->removeGrantsForResources($resourceClass, $resourceIdentifiers, $resourceType);
        }
    }

    /**
     * Use self::COLLECTION_RESOURCE_IDENTIFIER as resourceIdentifier for collection actions.
     *
     * @throws ApiError
     */
    public function addResourceActionGrant(string $resourceClass, string $resourceIdentifier,
        int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $action = null, ?Role $role = null,
        ?string $userIdentifier = null, ?string $groupIdentifier = null, ?string $dynamicGroupIdentifier = null): ResourceActionGrant
    {
        return $this->authorizationService->addResourceActionGrant(
            $resourceClass, $resourceIdentifier, $resourceType,
            $action, $role,
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
    public function getGrantedActionsPageForCurrentUser(
        ?string $resourceClass = null,
        ?string $resourceIdentifier = null,
        ?int $resourceType = self::RESOURCE_RESOURCE_TYPE,
        ?string $whereIsGrantedAction = null,
        bool $excludeCollectionResource = true,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array
    {
        return $this->authorizationService->getGrantedActionsPageForCurrentUser(
            $resourceClass, $resourceIdentifier, $resourceType,
            whereIsGrantedAction: $whereIsGrantedAction,
            excludeCollectionResource: $excludeCollectionResource,
            firstResultIndex: $firstResultIndex,
            maxNumResults: min($maxNumResults, self::MAX_NUM_RESULTS_MAX));
    }
}
