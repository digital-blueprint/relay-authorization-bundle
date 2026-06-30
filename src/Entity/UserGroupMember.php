<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupMemberProcessor;
use Dbp\Relay\AuthorizationBundle\Rest\UserGroupMemberProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @internal
 */
#[ApiResource(
    shortName: 'AuthorizationUserGroupMember',
    operations: [
        new Post(
            uriTemplate: '/authorization/user-group-members',
            openapi: new Operation(
                tags: ['Authorization'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'userGroup' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the AuthorizationUserGroup resource to add a member to',
                                        'example' => '/authorization/user-groups/{identifier}',
                                    ],
                                    'userIdentifier' => [
                                        'type' => 'string',
                                        'description' => 'The identifier of the user (person) to add as a member',
                                        'example' => '1234',
                                    ],
                                ],
                                'required' => ['userGroup', 'userIdentifier'],
                            ],
                            'example' => [
                                'user-group' => '/authorization/user-groups/{identifier}',
                                'userIdentifier' => '1234',
                            ],
                        ],
                    ]),
                ),
            ),
            processor: UserGroupMemberProcessor::class
        ),
        new Delete(
            uriTemplate: '/authorization/user-group-members/{identifier}',
            openapi: new Operation(
                tags: ['Authorization']
            ),
            provider: UserGroupMemberProvider::class,
            processor: UserGroupMemberProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => ['AuthorizationUserGroupMember:output'],
    ],
    denormalizationContext: [
        'groups' => ['AuthorizationUserGroupMember:input'],
    ],
)]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class UserGroupMember
{
    public const TABLE_NAME = 'authorization_user_group_members';

    public const USER_GROUP_IDENTIFIER_COLUMN = 'user_group_identifier';
    public const USER_IDENTIFIER_COLUMN = 'user_identifier';

    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', unique: true)]
    #[Groups(['AuthorizationUserGroupMember:output', 'AuthorizationUserGroup:output'])]
    private ?string $identifier = null;

    #[ApiProperty(
        description: 'The identifier of the AuthorizationUserGroup resource to add a member to',
        openapiContext: [
            'example' => '/authorization/user-groups/{identifier}',
        ]
    )]
    #[ORM\JoinColumn(name: self::USER_GROUP_IDENTIFIER_COLUMN, referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserGroup::class, inversedBy: 'members')]
    #[Groups(['AuthorizationUserGroupMember:input', 'AuthorizationUserGroupMember:output'])]
    private ?UserGroup $userGroup = null;

    /**
     * User type member.
     */
    #[ApiProperty(
        description: 'The identifier of the user (person) to add as a member',
        openapiContext: [
            'example' => '811EC3ACC0ADCA70',
        ]
    )]
    #[ORM\Column(name: self::USER_IDENTIFIER_COLUMN, type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationUserGroupMember:input', 'AuthorizationUserGroupMember:output', 'AuthorizationUserGroup:output'])]
    private ?string $userIdentifier = null;

    /**
     * Group type member. Disabled (not available over the API). Might be removed completely in the future.
     */
    #[ORM\JoinColumn(name: 'child_group_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserGroup::class)]
    private ?UserGroup $childGroup = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getUserGroup(): ?UserGroup
    {
        return $this->userGroup;
    }

    public function setUserGroup(?UserGroup $userGroup): void
    {
        $this->userGroup = $userGroup;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getChildGroup(): ?UserGroup
    {
        return $this->childGroup;
    }

    public function setChildGroup(?UserGroup $childGroup): void
    {
        $this->childGroup = $childGroup;
    }
}
