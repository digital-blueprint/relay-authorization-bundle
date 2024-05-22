<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\AvailableResourceClassActions;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<AvailableResourceClassActions>
 *
 * @internal
 */
class AvailableResourceClassActionsProvider extends AbstractDataProvider
{
    public const RESOURCE_CLASS_QUERY_PARAMETER = 'resourceClass';

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();

        $this->eventDispatcher = $eventDispatcher;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $resourceClass = $filters[self::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
        if ($resourceClass === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'query parameter \''.self::RESOURCE_CLASS_QUERY_PARAMETER.'\' is required',
                Common::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::RESOURCE_CLASS_QUERY_PARAMETER]);
        }
        $getActionsEvent = new GetAvailableResourceClassActionsEvent($resourceClass);
        $this->eventDispatcher->dispatch($getActionsEvent);

        $itemActions = $getActionsEvent->getItemActions();
        if ($itemActions !== null
            && !in_array(AuthorizationService::MANAGE_ACTION, $itemActions, true)) {
            $itemActions[] = AuthorizationService::MANAGE_ACTION;
        }
        $collectionActions = $getActionsEvent->getCollectionActions();
        if ($collectionActions !== null
            && !in_array(AuthorizationService::MANAGE_ACTION, $collectionActions, true)) {
            $collectionActions[] = AuthorizationService::MANAGE_ACTION;
        }

        return new AvailableResourceClassActions(
            $itemActions, $collectionActions);
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
