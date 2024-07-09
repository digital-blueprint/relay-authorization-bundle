<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

class Common
{
    public const RESOURCE_CLASS_QUERY_PARAMETER = 'resourceClass';
    public const RESOURCE_IDENTIFIER_QUERY_PARAMETER = 'resourceIdentifier';
    public const IS_NULL_FILTER = 'IS_NULL';
    public const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'authorization:required-parameter-missing';
}
