<?php

declare(strict_types=1);

namespace Dbp\Relay\AuhorizationBundle\Rest;

use Dbp\Relay\AuhorizationBundle\Entity\Group;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;

/**
 * @extends AbstractDataProvider<Group>
 */
class GroupProvider extends AbstractDataProvider
{
    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return null;
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
