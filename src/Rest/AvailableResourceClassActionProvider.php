<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassAction;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
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
        private readonly InternalResourceActionGrantService $internalResourceActionGrantService,
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
        $resourceClass = Common::getResourceClassFilter($filters);
        $resourceIdentifier = Common::getResourceIdentifierFilter($filters);

        $actionType = $resourceIdentifier !== null ?
            AvailableResourceClassAction::getActionTypeForResourceIdentifier($resourceIdentifier) :
            null;

        $availableResourceClassActionEntities =
            $this->internalResourceActionGrantService->getAvailableResourceClassActionEntities(
                $resourceClass,
                $actionType
            );

        $resourceClassesCurrentUserMayRead = $this->authorizationService->getResourceClassesCurrentUserIsAuthorizedToRead(
            0, AuthorizationService::MAX_NUM_RESULTS_DEFAULT);

        $filteredEntities = array_values(array_filter($availableResourceClassActionEntities,
            function (AvailableResourceClassAction $availableResourceClassAction) use ($resourceClassesCurrentUserMayRead) {
                return in_array($availableResourceClassAction->getResourceClass(), $resourceClassesCurrentUserMayRead, true);
            }
        ));

        return array_slice($filteredEntities, Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage));
    }
}
