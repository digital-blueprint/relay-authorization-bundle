<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class Common
{
    public const RESOURCE_CLASS_QUERY_PARAMETER = 'resourceClass';
    public const RESOURCE_IDENTIFIER_QUERY_PARAMETER = 'resourceIdentifier';
    public const RESOURCE_TYPE_QUERY_PARAMETER = 'resourceType';
    public const EXCLUDE_COLLECTION_RESOURCES_QUERY_PARAMETER = 'excludeCollectionResources';
    public const WHERE_IS_GRANTED_ACTION_QUERY_PARAMETER = 'whereIsGrantedActions';

    public const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'authorization:required-parameter-missing';

    public static function getResourceClassQueryParameter(array $filters, bool $required = false): ?string
    {
        $resourceClass = $filters[Common::RESOURCE_CLASS_QUERY_PARAMETER] ?? null;
        if ($required && null === $resourceClass) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'filter '.
                Common::RESOURCE_CLASS_QUERY_PARAMETER.' is required', self::REQUIRED_PARAMETER_MISSION_ERROR_ID);
        }

        return $resourceClass;
    }

    public static function getResourceIdentifierQueryParameter(array $filters, bool $required = false): ?string
    {
        $resourceIdentifier = $filters[Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER] ?? null;
        if ($required && null === $resourceIdentifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'filter '.
                Common::RESOURCE_IDENTIFIER_QUERY_PARAMETER.' is required', self::REQUIRED_PARAMETER_MISSION_ERROR_ID);
        }

        return $resourceIdentifier;
    }

    public static function getResourceTypeQueryParameter(array $filters, bool $required = false): ?int
    {
        $resourceType = $filters[Common::RESOURCE_TYPE_QUERY_PARAMETER] ?? null;
        if ($required && null === $resourceType) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'filter '.
                Common::RESOURCE_TYPE_QUERY_PARAMETER.' is required', self::REQUIRED_PARAMETER_MISSION_ERROR_ID);
        }

        return $resourceType;
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
