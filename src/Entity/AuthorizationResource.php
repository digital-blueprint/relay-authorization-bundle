<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\AuthorizationResourceProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationResource',
    operations: [
        new Get(
            uriTemplate: '/authorization/resources/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: AuthorizationResourceProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/resources',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: AuthorizationResourceProvider::class,
            parameters: [
                'resourceClass' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                    ],
                    description: 'The resource class to get the AuthorizationResource collection for',
                    required: false,
                ),
            ]
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationResource:output'],
    ],
)]
#[ORM\Table(name: 'authorization_resources')]
#[ORM\Entity]
class AuthorizationResource
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    #[Groups(['AuthorizationResource:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'resource_class', type: 'string', length: 40)]
    #[Groups(['AuthorizationResource:input', 'AuthorizationResource:output'])]
    private ?string $resourceClass = null;

    #[ORM\Column(name: 'resource_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResource:input', 'AuthorizationResource:output'])]
    private ?string $resourceIdentifier = null;

    #[Groups(['AuthorizationResource:output'])]
    private bool $writable = false;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }

    public function getWritable(): bool
    {
        return $this->writable;
    }

    public function setWritable(bool $writable): void
    {
        $this->writable = $writable;
    }
}
