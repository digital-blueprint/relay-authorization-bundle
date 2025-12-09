<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;

class AuthorizationResourceProvider extends AbstractDataProvider
{
    public function __construct(private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        $resourceClass = $this->getCurrentUriVariables()[Common::RESOURCE_CLASS_URI_VARIABLE_NAME];
        $resourceIdentifier = $this->getCurrentUriVariables()[Common::RESOURCE_IDENTIFIER_URI_VARIABLE_NAME];

        return $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            $resourceClass,
            $resourceIdentifier,
            maxNumResults: 1
        )[0] ?? null;
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->authorizationService->getAuthorizationResourcesCurrentUserIsAuthorizedToRead(
            $this->getCurrentUriVariables()[Common::RESOURCE_CLASS_URI_VARIABLE_NAME],
            null,
            firstResultIndex: Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage),
            maxNumResults: $maxNumItemsPerPage
        );
    }
}
