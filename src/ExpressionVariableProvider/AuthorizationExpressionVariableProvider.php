<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\ExpressionVariableProvider;

use Dbp\Relay\CoreBundle\Authorization\AuthorizationExpressionVariableProviderInterface;

class AuthorizationExpressionVariableProvider implements AuthorizationExpressionVariableProviderInterface
{
    public function __construct(
        private readonly AuthorizationExpressionVariable $authorizationExpressionVariable,
    ) {
    }

    public function getName(): string
    {
        return 'DbpRelayAuthorization';
    }

    public function getValue(): mixed
    {
        return $this->authorizationExpressionVariable;
    }
}
