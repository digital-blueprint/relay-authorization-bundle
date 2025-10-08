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
    #[ORM\JoinColumn(name: 'source_authorization_resource', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $sourceAuthorizationResource = null;

    #[ORM\Id]
    #[ORM\JoinColumn(name: 'target_authorization_resource', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AuthorizationResource::class)]
    private ?AuthorizationResource $targetAuthorizationResource = null;

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
