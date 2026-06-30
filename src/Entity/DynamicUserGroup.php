<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\DynamicUserGroupProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationDynamicUserGroup',
    operations: [
        new Get(
            uriTemplate: '/authorization/dynamic-user-groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: DynamicUserGroupProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/dynamic-user-groups',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: DynamicUserGroupProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationDynamicUserGroup:output'],
    ],
)]
class DynamicUserGroup
{
    #[Groups(['AuthorizationDynamicUserGroup:output'])]
    private ?string $identifier;

    public function __construct(?string $identifier = null)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }
}
