<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\GroupMemberProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\GroupMemberProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationGroupMember',
    operations: [
        //        new Get(
        //            uriTemplate: '/authorization/group-members/{identifier}',
        //            openapi: new Operation(
        //                tags: ['Authorization']
        //            ),
        //            provider: GroupMemberProvider::class
        //        ),
        //        new GetCollection(
        //            uriTemplate: '/authorization/group-members',
        //            openapi: new Operation(
        //                tags: ['Authorization'],
        //                parameters: [
        //                    new Parameter(
        //                        name: 'groupIdentifier',
        //                        in: 'query',
        //                        description: 'AuthorizationGroup identifier to get members of',
        //                        required: true,
        //                        schema: ['type' => 'string'],
        //                    ),
        //                ]
        //            ),
        //            provider: GroupMemberProvider::class,
        //        ),
        new Post(
            uriTemplate: '/authorization/group-members',
            openapi: new Operation(
                tags: ['Authorization'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'group' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the AuthorizationGroup resource to add a member to',
                                        'example' => '/authorization/groups/{identifier}',
                                    ],
                                    'userIdentifier' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the user (person) to add as a member',
                                        'example' => '811EC3ACC0ADCA70', // woody007
                                    ],
                                    'childGroup' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the AuthorizationGroup resource to add as a member',
                                        'example' => '/authorization/group/{identifier}',
                                    ],
                                ],
                                'required' => ['group'],
                            ],
                            'example' => [
                                'group' => '/authorization/groups/{identifier}',
                                'userIdentifier' => '811EC3ACC0ADCA70', // woody007
                            ],
                        ],
                    ]),
                ),
            ),
            processor: GroupMemberProcessor::class
        ),
        new Delete(
            uriTemplate: '/authorization/group-members/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: GroupMemberProvider::class,
            processor: GroupMemberProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationGroupMember:output'],
    ],
    denormalizationContext: [
        'groups' => ['AuthorizationGroupMember:input'],
    ],
)]
#[ORM\Table(name: 'authorization_group_members')]
#[ORM\Entity]
class GroupMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationGroupMember:output'])]
    private ?string $identifier = null;

    #[ApiProperty(
        description: 'The identifier of the AuthorizationGroup resource to add a member to',
        openapiContext: [
            'example' => '/authorization/groups/{identifier}',
        ]
    )]
    #[ORM\JoinColumn(name: 'parent_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'members')]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output'])]
    private ?Group $group = null;

    /**
     * User type member.
     */
    #[ApiProperty(
        description: 'The identifier of the user (person) to add as a member',
        openapiContext: [
            'example' => '811EC3ACC0ADCA70',
        ]
    )]
    #[ORM\Column(name: 'user_identifier', type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output', 'AuthorizationGroup:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type member.
     */
    #[ApiProperty(
        description: 'The identifier of the AuthorizationGroup resource to add as a member',
        openapiContext: [
            'example' => '/authorization/groups/{identifier}',
        ]
    )]
    #[ORM\JoinColumn(name: 'child_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[Groups(['AuthorizationGroupMember:input', 'AuthorizationGroupMember:output', 'AuthorizationGroup:output'])]
    private ?Group $childGroup = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): void
    {
        $this->group = $group;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getChildGroup(): ?Group
    {
        return $this->childGroup;
    }

    public function setChildGroup(?Group $childGroup): void
    {
        $this->childGroup = $childGroup;
    }
}
