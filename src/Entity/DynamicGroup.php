<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\DynamicGroupProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationDynamicGroup',
    operations: [
        new Get(
            uriTemplate: '/authorization/dynamic-groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: DynamicGroupProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/dynamic-groups',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: DynamicGroupProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationDynamicGroup:output'],
    ],
)]
#[ORM\Embeddable]
class DynamicGroup
{
    #[Groups(['AuthorizationDynamicGroup:output'])]
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
