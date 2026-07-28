<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionProvider;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AuthorizationAvailableResourceClassAction',
    operations: [
        new GetCollection(
            uriTemplate: '/authorization/available-resource-class-actions',
            openapi: new Operation(
                tags: ['Authorization'],
                parameters: [
                    new Parameter(
                        name: 'resourceClass',
                        in: 'query',
                        description: 'The resource class to get available actions for',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'resourceIdentifier',
                        in: 'query',
                        description: 'The resource identifier to get available actions for',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            provider: AvailableResourceClassActionProvider::class
        ),
    ],
    normalizationContext: [
        'groups' => [
            'AuthorizationAvailableResourceClassAction:output',
        ],
    ],
)]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class AvailableResourceClassAction
{
    public const TABLE_NAME = 'authorization_available_resource_class_actions';
    public const IDENTIFIER_COLUMN = 'identifier';
    public const RESOURCE_CLASS_COLUMN = 'resource_class';
    public const ACTION_COLUMN = 'action';
    public const ACTION_TYPE_COLUMN = 'action_type';

    public const ITEM_ACTION_TYPE = 0;
    public const COLLECTION_ACTION_TYPE = 1;

    #[ORM\Id]
    #[ORM\Column(name: self::IDENTIFIER_COLUMN, type: 'relay_authorization_uuid_binary', length: 16, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(name: self::RESOURCE_CLASS_COLUMN, type: 'string', length: 40, nullable: true)]
    #[Groups(['AuthorizationAvailableResourceClassAction:output'])]
    private ?string $resourceClass = null;

    #[ORM\Column(name: self::ACTION_COLUMN, type: 'string', length: 40, nullable: false)]
    #[Groups(['AuthorizationAvailableResourceClassAction:output'])]
    private ?string $action = null;

    #[ORM\Column(name: self::ACTION_TYPE_COLUMN, type: 'smallint', nullable: true)]
    #[Groups(['AuthorizationAvailableResourceClassAction:output'])]
    private ?int $actionType = null;

    #[ORM\OneToMany(targetEntity: AvailableResourceClassActionName::class, mappedBy: 'availableResourceClassAction')]
    #[Groups(['AuthorizationAvailableResourceClassAction:output'])]
    #[ApiProperty(genId: false)]
    private Collection $names;

    #[ORM\OneToMany(targetEntity: ResourceActionGrant::class, mappedBy: 'availableResourceClassAction')]
    private Collection $grants;

    #[ORM\OneToMany(targetEntity: RoleAction::class, mappedBy: 'availableResourceClassAction')]
    private Collection $roleActions;

    public static function getActionTypeForResourceIdentifier(string $effectiveResourceIdentifier): int
    {
        return $effectiveResourceIdentifier === InternalResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER ?
            self::COLLECTION_ACTION_TYPE :
            self::ITEM_ACTION_TYPE;
    }

    public function __construct()
    {
        $this->names = new ArrayCollection();
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getActionType(): ?int
    {
        return $this->actionType;
    }

    public function setActionType(?int $actionType): void
    {
        $this->actionType = $actionType;
    }

    public function getNames(): Collection
    {
        return $this->names;
    }

    public function setNames(Collection $names): void
    {
        $this->names = $names;
    }

    public function getGrants(): Collection
    {
        return $this->grants;
    }

    public function setGrants(Collection $grants): void
    {
        $this->grants = $grants;
    }

    public function getRoleActions(): Collection
    {
        return $this->roleActions;
    }

    public function setRoleActions(Collection $roleActions): void
    {
        $this->roleActions = $roleActions;
    }
}
