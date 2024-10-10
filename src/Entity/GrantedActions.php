<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;

class GrantedActions
{
    public const ID_SEPARATOR = ':';

    /**
     * @var string[]|null
     */
    #[ApiProperty(iris: ['https://schema.org/additionalProperty'])]
    #[Groups(['AuthorizationGrantedActions:output'])]
    private ?array $actions = null;

    #[ApiProperty(iris: ['https://schema.org/additionalProperty'])]
    #[Groups(['AuthorizationGrantedActions:output', 'AuthorizationGrantedActions:input'])]
    private ?string $resourceClass = null;

    #[ApiProperty(iris: ['https://schema.org/additionalProperty'])]
    #[Groups(['AuthorizationGrantedActions:output', 'AuthorizationGrantedActions:input'])]
    private ?string $resourceIdentifier = null;

    /**
     * @throws ApiError
     */
    public static function fromCompositeIdentifier(string $id): self
    {
        $parts = explode(self::ID_SEPARATOR, $id, 2);
        if (count($parts) !== 2) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Identifier must contain a \''.self::ID_SEPARATOR.'\' separator');
        }

        $grantedActions = new self();
        $grantedActions->setResourceClass($parts[0] !== '' ? $parts[0] : null);
        $grantedActions->setResourceIdentifier($parts[1] !== '' ? $parts[1] : null);

        return $grantedActions;
    }

    #[ApiProperty(identifier: true)]
    public function getIdentifier(): ?string
    {
        return $this->resourceClass.self::ID_SEPARATOR.$this->resourceIdentifier;
    }

    public function getActions(): ?array
    {
        return $this->actions;
    }

    public function setActions(?array $actions): void
    {
        $this->actions = $actions;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceIdentifier(): ?string
    {
        return $this->resourceIdentifier;
    }

    public function setResourceIdentifier(?string $resourceIdentifier): void
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }
}
