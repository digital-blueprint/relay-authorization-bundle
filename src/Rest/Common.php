<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class Common
{
    public const RESOURCE_CLASS_URI_VARIABLE_NAME = 'resourceClass';
    public const RESOURCE_CLASS_QUERY_PARAMETER = 'resourceClass';
    public const RESOURCE_IDENTIFIER_QUERY_PARAMETER = 'resourceIdentifier';
    public const RESOURCE_TYPE_QUERY_PARAMETER = 'resourceType';
    public const EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER = 'excludeCollectionResources';
    public const WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER = 'whereIsGrantedActions';

    public const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'authorization:required-parameter-missing';

    public static function getResourceClassQueryParameter(array $filters): ?string
    {
        return $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
    }

    public static function getResourceIdentifierQueryParameter(array $filters): ?string
    {
        return $filters[Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER] ?? null;
    }

    public static function getResourceTypeQueryParameter(array $filters): ?int
    {
        return $filters[Common::RESOURCE_TYPE_QUERY_PARAMETER] ?? null;
    }

    /**
     * Default: true.
     */
    public static function getExcludeCollectionResourcesFilter(array $filters): bool
    {
        $boolValue = true;
        if (($value = $filters[self::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER] ?? null) !== null) {
            if (null === ($boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'filter '.
                    self::EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER.' must be of type boolean');
            }
        }

        return $boolValue;
    }

    public static function getWhereIsGrantedActionFilter(array $filters): ?string
    {
        return $filters[self::WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER] ?? null;
    }
}
