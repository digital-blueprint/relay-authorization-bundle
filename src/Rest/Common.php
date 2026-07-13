<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;

class Common
{
    public const RESOURCE_CLASS_URI_VARIABLE_NAME = 'resourceClass';
    public const RESOURCE_IDENTIFIER_URI_VARIABLE_NAME = 'resourceIdentifier';
    public const RESOURCE_CLASS_QUERY_PARAMETER = 'resourceClass';
    public const RESOURCE_IDENTIFIER_QUERY_PARAMETER = 'resourceIdentifier';
    public const RESOURCE_TYPE_QUERY_PARAMETER = 'resourceType';
    public const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'authorization:required-parameter-missing';

    public static function getResourceClassFilter(array $filters): ?string
    {
        return $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
    }

    public static function getResourceIdentifierFilter(array $filters): ?string
    {
        return $filters[Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER] ?? null;
    }

    public static function getResourceTypeFilter(array $filters): int
    {
        return $filters[Common::RESOURCE_TYPE_QUERY_PARAMETER] ?? AuthorizationService::RESOURCE_RESOURCE_TYPE;
    }
}
