<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\ExpressionVariableProvider;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Service\GroupService;

class AuthorizationExpressionVariable
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly GroupService $groupService,
    ) {
    }

    public function isGranted(string $resourceClass, string $resourceIdentifier, string $action): bool
    {
        return $this->authorizationService->isCurrentUserGranted($resourceClass, $resourceIdentifier, $action);
    }

    public function isMemberOfGroup(string $groupIdentifier): bool
    {
        $userIdentifier = $this->authorizationService->getUserIdentifier();
        if ($userIdentifier === null) {
            return false;
        }

        return $this->groupService->isUserMemberOfGroup($userIdentifier, $groupIdentifier);
    }

    public function isMemberOfDynamicGroup(string $dynamicGroupIdentifier): bool
    {
        return $this->authorizationService->isCurrentUserMemberOfDynamicGroup($dynamicGroupIdentifier);
    }
}
