<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="authorization_groups")
 */
class Group
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="relay_authorization_uuid_binary", unique=true)
     *
     * @Groups({"AuthorizationGroup:output"})
     */
    private ?string $identifier = null;

    /**
     * @ORM\Column(name="name", type="string", length=64)
     *
     * @Groups({"AuthorizationGroup:input", "AuthorizationGroup:output"})
     */
    private ?string $name = null;

    /**
     * @ORM\OneToMany(targetEntity="GroupMember", mappedBy="group")
     *
     * @Groups({"AuthorizationGroup:output"})
     */
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
