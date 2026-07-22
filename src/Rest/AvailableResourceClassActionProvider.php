<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

/**
 * @extends AbstractDataProvider<AvailableResourceClassAction>
 *
 * @internal
 */
class AvailableResourceClassActionProvider extends AbstractDataProvider
{
    protected static string $identifierName = 'resourceClass';

    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        throw new \RuntimeException('Get item operation not available');
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $resourceClass = Common::getResourceClassQueryParameter($filters, true);
        $resourceIdentifier = Common::getResourceIdentifierQueryParameter($filters, true);
        $resourceType = Common::getResourceTypeQueryParameter($filters, true);

        return
            $this->authorizationService->getAvailableResourceClassActionsUserMayGrant(
                $resourceClass,
                $resourceIdentifier,
                $resourceType,
                Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
                $maxNumItemsPerPage
            );
    }
}
