<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @internal
 */
#[ORM\Table(name: 'authorization_grant_inheritance')]
#[ORM\Entity]
class GrantInheritance
{
    #[ORM\Id]
    #[ORM\Column(type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'source_authorization_resource', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $sourceAuthorizationResource = null;

    #[ORM\JoinColumn(name: 'target_authorization_resource', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $targetAuthorizationResource = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getSourceAuthorizationResource(): ?AuthorizationResource
    {
        return $this->sourceAuthorizationResource;
    }

    public function setSourceAuthorizationResource(?AuthorizationResource $sourceAuthorizationResource): void
    {
        $this->sourceAuthorizationResource = $sourceAuthorizationResource;
    }

    public function getTargetAuthorizationResource(): ?AuthorizationResource
    {
        return $this->targetAuthorizationResource;
    }

    public function setTargetAuthorizationResource(?AuthorizationResource $targetAuthorizationResource): void
    {
        $this->targetAuthorizationResource = $targetAuthorizationResource;
    }
}
