<?php

declare(strict_types=1);

namespace Dbp\Relay\AuhorizationBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;

class GroupProcessor extends AbstractDataProcessor
{
    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }
}
