<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AuthorizationUserGroup',
    operations: [
        new Get(
            uriTemplate: '/authorization/user-groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: UserGroupProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/user-groups',
            openapi: new Operation(
                tags: ['Authorization'],
                parameters: [
                    new Parameter(
                        name: 'search',
                        in: 'query',
                        description: 'A substring to search for in the group name',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'getChildGroupCandidatesForGroupIdentifier',
                        in: 'query',
                        description: 'Only return groups that can be members (child groups) of the given group',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ]
            ),
            provider: UserGroupProvider::class,
        ),
        new Post(
            uriTemplate: '/authorization/user-groups',
            openapi: new Operation(
                tags: ['Authorization'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                    ],
                                ],
                                'required' => ['name'],
                            ],
                            'example' => [
                                'name' => 'My Group',
                            ],
                        ],
                    ]),
                ),
            ),
            processor: UserGroupProcessor::class
        ),
        new Patch(
            uriTemplate: '/authorization/user-groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/merge-patch+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                            'example' => [
                                'name' => 'My Group',
                            ],
                        ],
                    ]),
                )
            ),
            provider: UserGroupProvider::class,
            processor: UserGroupProcessor::class
        ),
        new Delete(
            uriTemplate: '/authorization/user-groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: UserGroupProvider::class,
            processor: UserGroupProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationUserGroup:output'],
    ],
    denormalizationContext: [
        'groups' => ['AuthorizationUserGroup:input'],
    ],
)]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class UserGroup
{
    public const TABLE_NAME = 'authorization_user_groups';

    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationUserGroup:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'name', type: 'string', length: 128)]
    #[Groups(['AuthorizationUserGroup:input', 'AuthorizationUserGroup:output'])]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: UserGroupMember::class, mappedBy: 'userGroup')]
    #[Groups(['AuthorizationUserGroup:output'])]
    #[ApiProperty(genId: false)]
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function setMembers(Collection $members): void
    {
        $this->members = $members;
    }
}
