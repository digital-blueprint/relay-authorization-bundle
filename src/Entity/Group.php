<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\GroupProvider;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationGroup',
    operations: [
        new Get(
            uriTemplate: '/authorization/groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: GroupProvider::class
        ),
        new GetCollection(
            uriTemplate: '/authorization/groups',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: GroupProvider::class,
            parameters: [
                'search' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                    ],
                    description: 'A substring to search for in the group name',
                    required: false,
                ),
                'getChildGroupCandidatesForGroupIdentifier' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                    ],
                    description: 'Only return groups that can be members (child groups) of the given group',
                    required: false
                ),
            ]
        ),
        new Post(
            uriTemplate: '/authorization/groups',
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
            processor: GroupProcessor::class
        ),
        new Patch(
            uriTemplate: '/authorization/groups/{identifier}',
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
            provider: GroupProvider::class,
            processor: GroupProcessor::class
        ),
        new Delete(
            uriTemplate: '/authorization/groups/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: GroupProvider::class,
            processor: GroupProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationGroup:output'],
    ],
    denormalizationContext: [
        'groups' => ['AuthorizationGroup:input'],
    ],
)]
#[ORM\Table(name: 'authorization_groups')]
#[ORM\Entity]
class Group
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationGroup:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'name', type: 'string', length: 64)]
    #[Groups(['AuthorizationGroup:input', 'AuthorizationGroup:output'])]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group')]
    #[Groups(['AuthorizationGroup:output'])]
    private ?PersistentCollection $members = null;

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

    public function getMembers(): PersistentCollection|array
    {
        return $this->members ?? [];
    }

    public function setMembers(?PersistentCollection $members): void
    {
        $this->members = $members;
    }
}
