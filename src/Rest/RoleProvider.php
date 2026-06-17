<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\Role;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
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
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->internalResourceActionGrantService->getRoleByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $getRoleFilters = [];
        if ($resourceClass = $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null) {
            $getRoleFilters[Common::RESOURCE_CLASS_QUERY_PARAMETER] = $resourceClass;
        }
        if ($resourceIdentifier = $filters[Common::ACTION_TYPE_QUERY_PARAMETER] ?? null) {
            $getRoleFilters[Common::ACTION_TYPE_QUERY_PARAMETER] = $resourceIdentifier;
        }

        return $this->internalResourceActionGrantService->getRoles(
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            $maxNumItemsPerPage,
            $getRoleFilters);
    }
}
