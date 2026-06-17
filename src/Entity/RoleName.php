<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class RoleName
{
    public const TABLE_NAME = 'authorization_role_names';

    public const ROLE_IDENTIFIER_COLUMN = 'role_identifier';
    public const LANGUAGE_TAG_COLUMN_NAME = 'language_tag';
    public const NAME_COLUMN_NAME = 'name';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::ROLE_IDENTIFIER_COLUMN, referencedColumnName: Role::IDENTIFIER_COLUMN_NAME, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'roleNames')]
    private ?Role $role = null;

    #[ORM\Id]
    #[ORM\Column(name: self::LANGUAGE_TAG_COLUMN_NAME, type: 'string', length: 2)]
    private ?string $languageTag = null;

    #[ORM\Column(name: self::NAME_COLUMN_NAME, type: 'string', length: 64)]
    private ?string $name = null;

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): void
    {
        $this->role = $role;
    }

    public function getLanguageTag(): ?string
    {
        return $this->languageTag;
    }

    public function setLanguageTag(?string $languageTag): void
    {
        $this->languageTag = $languageTag;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
