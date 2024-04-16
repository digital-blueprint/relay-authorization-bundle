<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="authorization_resource_actions")
 */
class ResourceAction
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=50)
     *
     * @Groups({"AuthorizationResourceAction:output"})
     */
    private ?string $identifier = null;

    /**
     * @ORM\Column(name="namespace", type="string", length=64)
     *
     * @Groups({"AuthorizationResourceAction:input", "AuthorizationResourceAction:output"})
     */
    private ?string $namespace = null;

    /**
     * @ORM\Column(name="resource_identifier", type="string", length=64)
     *
     * @Groups({"AuthorizationResourceAction:input", "AuthorizationResourceAction:output"})
     */
    private ?string $resourceIdentifier = null;

    /**
     * @ORM\Column(name="action", type="string", length=64)
     *
     * @Groups({"AuthorizationResourceAction:input", "AuthorizationResourceAction:output"})
     */
    private ?string $action = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setNamespace(?string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }
}
