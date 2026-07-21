<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<Role>
 *
 * @internal
 */
class RoleProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        throw new \RuntimeException('Get item operation is not available');
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $resourceClass = Common::getResourceClassQueryParameter($filters, true);
        $resourceIdentifier = Common::getResourceIdentifierQueryParameter($filters, true);
        $resourceType = Common::getResourceTypeQueryParameter($filters, true);

        return $this->authorizationService->getRolesCurrentUserMayGrant(
            $resourceClass,
            $resourceIdentifier,
            $resourceType,
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            $maxNumItemsPerPage
        );
    }
}
