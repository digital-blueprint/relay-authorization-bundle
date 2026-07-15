<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<ResourceActionGrant>
 *
 * @internal
 */
class ResourceActionGrantProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->authorizationService->getResourceActionGrantByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->authorizationService->getResourceActionGrantsCurrentUserIsAuthorizedToRead(
            Common::getResourceClassQueryParameter($filters),
            Common::getResourceIdentifierQueryParameter($filters),
            Common::getResourceTypeQueryParameter($filters),
            firstResultIndex: Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            maxNumResults: $maxNumItemsPerPage);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGrant($item);
    }
}
