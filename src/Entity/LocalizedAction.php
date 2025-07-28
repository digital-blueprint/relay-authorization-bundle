<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'AuthorizationLocalizedAction',
    normalizationContext: [
        'groups' => ['AuthorizationLocalizedAction:output'],
    ],
)]
class LocalizedAction
{
    #[ApiProperty(identifier: true, openapiContext: [
        'type' => 'string',
        'example' => 'edit',
    ])]
    #[Groups(['AuthorizationLocalizedAction:output'])]
    private ?string $identifier;

    /**
     * @var array<string, string>|null
     */
    #[ApiProperty(
        description: 'Mapping of language tags to localized action names.',
        openapiContext: [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'example' => ['en' => 'Edit', 'de' => 'Editieren'],
        ])]
    #[Groups(['AuthorizationLocalizedAction:output'])]
    private ?array $names;

    public function __construct(?string $identifier = null, ?array $names = null)
    {
        $this->identifier = $identifier;
        $this->names = $names;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getNames(): ?array
    {
        return $this->names;
    }

    public function setNames(?array $names): void
    {
        $this->names = $names;
    }
}
