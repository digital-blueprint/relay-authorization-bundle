<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
class AvailableResourceClassActions
{
    private string $identifier = 'available-resource-class-actions';

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private array $availableResourceItemActions;

    /**
     * @var string[]
     */
    #[Groups(['AuthorizationAvailableResourceClassActions:output'])]
    private array $availableResourceCollectionActions;

    public function __construct(array $availableResourceItemActions, array $availableResourceCollectionActions)
    {
        $this->availableResourceItemActions = $availableResourceItemActions;
        $this->availableResourceCollectionActions = $availableResourceCollectionActions;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
    }

    /**
     * @return string[]
     */
    public function getAvailableResourceItemActions(): array
    {
        return $this->availableResourceItemActions;
    }

    /**
     * @return string[]
     */
    public function getAvailableResourceCollectionActions(): array
    {
        return $this->availableResourceCollectionActions;
    }
}
