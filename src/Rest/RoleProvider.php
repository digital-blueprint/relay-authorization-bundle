<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
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
        throw new \RuntimeException('Get item operation is not available');
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $resourceClass = $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
        $resourceIdentifier = $filters[Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER] ?? null;

        return $this->internalResourceActionGrantService->getRoles(
            $resourceClass,
            $resourceIdentifier !== null ?
                AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier) :
                null,
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            $maxNumItemsPerPage
        );
    }
}
