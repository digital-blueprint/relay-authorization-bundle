<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractDataProvider<GrantedActions>
 *
 * @internal
 */
class GrantedActionsProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * @throws ApiError
     */
    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $grantedActions = GrantedActions::fromCompositeIdentifier($id);
        if ($grantedActions->getResourceClass() === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Resource class is mandatory');
        }

        $grantedActions->setActions($grantedActions->getResourceIdentifier() !== null ?
            $this->authorizationService->getResourceItemActionsForCurrentUser(
                $grantedActions->getResourceClass(), $grantedActions->getResourceIdentifier()) :
            $this->authorizationService->getResourceCollectionActionsForCurrentUser(
                $grantedActions->getResourceClass()));

        return $grantedActions;
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        throw new \RuntimeException('operation not available');
    }
}
