<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Resource;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;

/**
 * @extends AbstractDataProvider<Resource>
 *
 * @internal
 */
class ResourceProvider extends AbstractDataProvider
{
    private InternalResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(InternalResourceActionGrantService $resourceActionService, AuthorizationService $authorizationService)
    {
        $this->resourceActionGrantService = $resourceActionService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->resourceActionGrantService->getResource($id, $options);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $currentUserIdentifier = $this->getUserIdentifier();

        return $currentUserIdentifier !== null ?
            $this->resourceActionGrantService->getResourcesUserIsAuthorizedToRead(
                $currentPageNumber, $maxNumItemsPerPage, $currentUserIdentifier) : [];
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof Resource);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGrant($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
