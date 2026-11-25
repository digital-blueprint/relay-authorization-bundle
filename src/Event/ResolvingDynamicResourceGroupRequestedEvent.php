<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ResolvingDynamicResourceGroupRequestedEvent extends Event
{
    /**
     * @var array<string, array<string, ?array<int, string>>>
     */
    private array $resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap = [];

    public function requestDynamicResourceGroupResolution(string $resourceClass, string $resourceGroupIdentifier): void
    {
        $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[$resourceClass][$resourceGroupIdentifier] = null;
    }

    public function isResolvingDynamicResourceGroupRequested(): bool
    {
        return false === empty($this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap);
    }

    /**
     * @return string[]
     */
    public function getRequestedResourceClasses(): array
    {
        return array_keys($this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap);
    }

    public function getRequestedDynamicResourceGroupIdentifiers(string $resourceClass): array
    {
        return array_keys($this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[$resourceClass] ?? []);
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
