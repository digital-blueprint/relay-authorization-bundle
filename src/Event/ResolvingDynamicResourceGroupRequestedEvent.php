<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ResolvingDynamicResourceGroupRequestedEvent extends Event
{
    /**
     * @param array<string, array<string, ?array<int, string>>> $resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap
     */
    public function __construct(
        private array $resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap
    ) {
    }

    /**
     * @return array<string, array<string, ?array<int, string>>>
     */
    public function getResourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap(): array
    {
        return $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap;
    }

    public function getMemberResourceIdentifiers(string $resourceClass, string $resourceGroupIdentifier): ?array
    {
        return $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[$resourceClass][$resourceGroupIdentifier] ?? null;
    }

    public function setMemberResourceIdentifiers(
        string $resourceClass,
        string $resourceGroupIdentifier,
        array $memberResourceIdentifiers
    ): void {
        $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[$resourceClass][$resourceGroupIdentifier] = $memberResourceIdentifiers;
    }
}
