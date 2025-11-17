<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @internal
 */
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class AvailableResourceClassActionName
{
    public const TABLE_NAME = 'authorization_available_resource_class_action_names';
    public const AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN_NAME = 'available_resource_class_action_identifier';
    public const LANGUAGE_TAG_COLUMN_NAME = 'language_tag';
    public const NAME_COLUMN_NAME = 'name';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::AVAILABLE_RESOURCE_CLASS_ACTION_IDENTIFIER_COLUMN_NAME, referencedColumnName: AvailableResourceClassAction::IDENTIFIER_COLUMN_NAME, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: AvailableResourceClassAction::class, inversedBy: 'names')]
    private ?AvailableResourceClassAction $availableResourceClassAction = null;

    #[ORM\Id]
    #[ORM\Column(name: self::LANGUAGE_TAG_COLUMN_NAME, type: 'string', length: 2)]
    private ?string $languageTag = null;

    #[ORM\Column(name: self::NAME_COLUMN_NAME, type: 'string', length: 64)]
    private ?string $name = null;

    public function getAvailableResourceClassAction(): ?AvailableResourceClassAction
    {
        return $this->availableResourceClassAction;
    }

    public function setAvailableResourceClassAction(?AvailableResourceClassAction $availableResourceClassAction): void
    {
        $this->availableResourceClassAction = $availableResourceClassAction;
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
