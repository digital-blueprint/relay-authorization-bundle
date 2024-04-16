<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;

/**
 * @extends AbstractDataProvider<ResourceActionGrant>
 */
class ResourceActionGrantProvider extends AbstractDataProvider
{
    private ResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(ResourceActionGrantService $resourceActionService, AuthorizationService $authorizationService)
    {
        $this->resourceActionGrantService = $resourceActionService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->resourceActionGrantService->getResourceActionGrant($id, $options);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->resourceActionGrantService->getResourceActionGrantsUserIsAuthorizedToRead(
            $currentPageNumber, $maxNumItemsPerPage, $this->getUserIdentifier());
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGrant($item);
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return true;
    }
}
