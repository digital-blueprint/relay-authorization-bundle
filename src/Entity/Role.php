<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Rest\RoleProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AuthorizationRole',
    operations: [
        new Get(
            uriTemplate: '/authorization/roles/{identifier}',
            openapi: new Operation(
                tags: ['Authorization'],
                parameters: [
                    new Parameter(
                        name: 'identifier',
                        in: 'path',
                        description: 'The identifier of the role to get',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            provider: RoleProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/roles',
            openapi: new Operation(
                tags: ['Authorization'],
                parameters: [
                    new Parameter(
                        name: 'resourceClass',
                        in: 'query',
                        description: 'The resource class to get roles for',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'resourceIdentifier',
                        in: 'query',
                        description: 'The resource identifier to get roles for',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'resourceType',
                        in: 'query',
                        description: 'The resource type to get grants for: 0 = RESOURCE_RESOURCE_TYPE, 1 = RESOURCE_GROUP_RESOURCE_TYPE (default: 0)',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'enum' => [
                                AuthorizationService::RESOURCE_RESOURCE_TYPE,
                                AuthorizationService::RESOURCE_GROUP_RESOURCE_TYPE,
                            ],
                        ],
                    ),
                ],
            ),
            provider: RoleProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationRole:output'],
    ],
)]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class Role
{
    public const TABLE_NAME = 'authorization_roles';

    public const IDENTIFIER_COLUMN_NAME = 'identifier';

    #[ORM\Id]
    #[ORM\Column(name: self::IDENTIFIER_COLUMN_NAME, type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    #[Groups(['AuthorizationRole:output'])]
    private ?string $identifier = null;

    #[ORM\OneToMany(targetEntity: RoleName::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['AuthorizationRole:output'])]
    #[ApiProperty(genId: false)]
    private Collection $roleNames;

    #[ORM\OneToMany(targetEntity: RoleAction::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roleActions;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'role', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $resourceActionGrants;

    public function __construct()
    {
        $this->roleNames = new ArrayCollection();
        $this->roleActions = new ArrayCollection();
        $this->resourceActionGrants = new ArrayCollection();
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getRoleNames(): Collection
    {
        return $this->roleNames;
    }

    public function setRoleNames(Collection $roleNames): void
    {
        $this->roleNames = $roleNames;
    }

    public function getRoleActions(): Collection
    {
        return $this->roleActions;
    }

    public function setRoleActions(Collection $roleActions): void
    {
        $this->roleActions = $roleActions;
    }

    public function getResourceActionGrants(): Collection
    {
        return $this->resourceActionGrants;
    }

    public function setResourceActionGrants(Collection $resourceActionGrants): void
    {
        $this->resourceActionGrants = $resourceActionGrants;
    }
}
