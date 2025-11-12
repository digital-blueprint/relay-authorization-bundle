<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @internal
 */
#[ORM\Table(name: 'group_authorization_resource_members')]
#[ORM\Entity]
class GroupAuthorizationResourceMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'group_authorization_resource_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $groupAuthorizationResource = null;

    #[ORM\JoinColumn(name: 'member_authorization_resource_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $memberAuthorizationResource = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getGroupAuthorizationResource(): ?AuthorizationResource
    {
        return $this->groupAuthorizationResource;
    }

    public function setGroupAuthorizationResource(?AuthorizationResource $groupAuthorizationResource): void
    {
        $this->groupAuthorizationResource = $groupAuthorizationResource;
    }

    public function getMemberAuthorizationResource(): ?AuthorizationResource
    {
        return $this->memberAuthorizationResource;
    }

    public function setMemberAuthorizationResource(?AuthorizationResource $memberAuthorizationResource): void
    {
        $this->memberAuthorizationResource = $memberAuthorizationResource;
    }
}
