<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\API;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

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
            $resourceClass, $resourceIdentifier, $this->getCurrentUserIdentifier(true));
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
     * @return ResourceAction[]
     *
     * @throws ApiError
     */
    public function getGrantedResourceActions(string $resourceClass, ?string $resourceIdentifier = null,
        ?array $actions = null, int $currentPageNumber = 1, int $maxNumItemsPerPage = 1024): array
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier(false);

        $internalResourceActionGrants = $currentUserIdentifier !== null ?
            $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                $resourceClass, $resourceIdentifier, $actions, $currentUserIdentifier, $currentPageNumber, $maxNumItemsPerPage) :
            [];

        $resourceActionGrants = [];
        foreach ($internalResourceActionGrants as $internalResourceActionGrant) {
            $resourceActionGrants[] = new ResourceAction(
                $internalResourceActionGrant->getAuthorizationResource()->getResourceIdentifier(),
                $internalResourceActionGrant->getAction()
            );
        }

        return $resourceActionGrants;
    }

    private function getCurrentUserIdentifier(bool $throwIfNull): ?string
    {
        $currentUserIdentifier = $this->authorizationService->getUserIdentifier();
        if ($currentUserIdentifier === null && $throwIfNull) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN,
                'a user identifier is required for authorization');
        }

        return $currentUserIdentifier;
    }
}
