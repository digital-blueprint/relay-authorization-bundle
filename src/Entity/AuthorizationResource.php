<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use Dbp\Relay\AuthorizationBundle\Rest\AuthorizationResourceProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationResource',
    operations: [
        new Get(
            uriTemplate: '/authorization/resource/{resourceClass}/{resourceIdentifier}',
            openapi: new Operation(
                tags: ['Authorization'],
            ),
            normalizationContext: [
                'groups' => [
                    'AuthorizationResource:output',
                    'AuthorizationResource:get_item_only_output',
                    'AuthorizationResourceActionGrant:output',
                ],
                'jsonld_embed_context' => true,
            ],
            provider: AuthorizationResourceProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/authorization/resource/{resourceClass}',
            openapi: new Operation(
                tags: ['Authorization'],
            ),
            provider: AuthorizationResourceProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationResource:output'],
        'jsonld_embed_context' => true,
    ],
)]
#[ORM\Table(name: 'authorization_resources')]
#[ORM\Entity]
class AuthorizationResource
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(name: 'resource_class', type: 'string', length: 40)]
    #[Groups(['AuthorizationResource:output'])]
    private ?string $resourceClass = null;

    #[ORM\Column(name: 'resource_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationResource:output'])]
    private ?string $resourceIdentifier = null;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'authorizationResource')]
    #[Groups(['AuthorizationResource:get_item_only_output'])]
    private Collection $resourceActionGrants;

    #[Groups(['AuthorizationResource:get_item_only_output'])]
    private ?array $grantedActions = null;

    public function __construct()
    {
        $this->resourceActionGrants = new ArrayCollection();
    }

    #[Ignore]
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

    public function getResourceActionGrants(): Collection
    {
        return $this->resourceActionGrants;
    }

    public function setResourceActionGrants(Collection $resourceActionGrants): void
    {
        $this->resourceActionGrants = $resourceActionGrants;
    }

    public function getGrantedActions(): ?array
    {
        return $this->grantedActions;
    }

    public function setGrantedActions(?array $grantedActions): void
    {
        $this->grantedActions = $grantedActions;
    }
}
