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
        return $this->authorizationService->getResourceActionGrantsUserIsAuthorizedToRead(
            self::getResourceClassFilter($filters),
            self::getResourceIdentifierFilter($filters),
            Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        assert($item instanceof ResourceActionGrant);

        return $this->authorizationService->isCurrentUserAuthorizedToReadGrant($item);
    }

    private static function getResourceClassFilter(array $filters): ?string
    {
        return $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
    }

    private static function getResourceIdentifierFilter(array $filters): ?string
    {
        return match ($filter = $filters[Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER] ?? null) {
            Common::IS_NULL_FILTER => AuthorizationService::IS_NULL,
            default => $filter,
        };
    }
}
