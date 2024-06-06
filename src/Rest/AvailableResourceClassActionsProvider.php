<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActions;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<AvailableResourceClassActions>
 *
 * @internal
 */
class AvailableResourceClassActionsProvider extends AbstractDataProvider
{
    private InternalResourceActionGrantService $resourceActionGrantService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $resourceClass = $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
        if ($resourceClass === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'query parameter \''.Common::RESOURCE_CLASS_QUERY_PARAMETER.'\' is required',
                Common::REQUIRED_PARAMETER_MISSION_ERROR_ID, [Common::RESOURCE_CLASS_QUERY_PARAMETER]);
        }

        [$itemActions, $collectionActions] =
            $this->resourceActionGrantService->getAvailableResourceClassActions($resourceClass);

        return new AvailableResourceClassActions($itemActions, $collectionActions);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return [];
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }
}
