<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionsProvider;
use Dbp\Relay\CoreBundle\User\UserAttributeException;
use Dbp\Relay\CoreBundle\User\UserAttributeProviderInterface;

readonly class UserAttributeProvider implements UserAttributeProviderInterface
{
    public const SEPARATOR = '.';

    private const USER_ATTRIBUTE_NAME_PATTERN = '/^([a-zA-Z0-9_-]+)(\.[a-zA-Z0-9_-]+)?\.(\w+)$/';

    public function __construct(
        private AuthorizationService $authorizationService,
        private AvailableResourceClassActionsProvider $availableResourceClassActionsProvider)
    {
    }

    /**
     * Returns true if
     * - the given attribute name matches the defined regex pattern
     * - it contains a resource class and action (and optionally a resource identifier)
     * - the action is found in the set of available actions for the resource class
     *  and false otherwise.
     *
     * DESIGN NOTE: There is no check if a given resource identifier is found in the database.
     */
    public function hasUserAttribute(string $name): bool
    {
        $resourceClass = $action = '';
        $resourceIdentifier = null;
        if ($this->parseAttributeName($name, $resourceClass, $resourceIdentifier, $action)) {
            $availableResourceClassActions =
                $this->availableResourceClassActionsProvider->getAvailableResourceClassActions($resourceClass);

            return $resourceIdentifier !== null ?
                ($itemActions = $availableResourceClassActions->getItemActions()) && in_array($action, $itemActions, true) :
                ($collectionActions = $availableResourceClassActions->getCollectionActions()) && in_array($action, $collectionActions, true);
        }

        return false;
    }

    public function getUserAttribute(?string $userIdentifier, string $name): mixed
    {
        if ($userIdentifier === null) {
            return false;
        }

        $resourceClass = $action = '';
        $resourceIdentifier = null;
        if (false === $this->parseAttributeName($name, $resourceClass, $resourceIdentifier, $action)) {
            throw new UserAttributeException("user attribute '$name' undefined",
                UserAttributeException::USER_ATTRIBUTE_UNDEFINED);
        }

        // a null resource identifier is used for collection actions
        return $this->authorizationService->isCurrentUserGranted($resourceClass, $resourceIdentifier, $action);
    }

    private function parseAttributeName(string $name, ?string &$resourceClass, ?string &$resourceIdentifier, ?string &$action): bool
    {
        if (preg_match(self::USER_ATTRIBUTE_NAME_PATTERN, $name, $matches)) {
            $resourceClass = $matches[1];
            $resourceIdentifier = $matches[2] ? ltrim($matches[2], '.') : null;
            $action = $matches[3];

            return $resourceClass && $action;
        }

        return false;
    }
}
