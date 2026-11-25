<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\ResolvingDynamicResourceGroupRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestResolvingDynamicResourceGroupRequestedEventSubscriber implements EventSubscriberInterface
{
    private array $resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap = [];

    public static function getSubscribedEvents(): array
    {
        return [
            ResolvingDynamicResourceGroupRequestedEvent::class => 'onResolvingDynamicResourceGroupRequestedEvent',
        ];
    }

    public function onResolvingDynamicResourceGroupRequestedEvent(
        ResolvingDynamicResourceGroupRequestedEvent $event
    ): void {
        foreach ($event->getRequestedResourceClasses() as $resourceClass) {
            foreach ($event->getRequestedDynamicResourceGroupIdentifiers($resourceClass) as $dynamicResourceGroupIdentifier) {
                $identifiers = $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[$resourceClass][$dynamicResourceGroupIdentifier] ?? null;
                if (null !== $identifiers) {
                    $event->setMemberResourceIdentifiers(
                        $resourceClass,
                        $dynamicResourceGroupIdentifier,
                        $identifiers
                    );
                }
            }
        }
    }

    public function setMemberResourceIdentifiers(
        string $resourceClass,
        string $resourceGroupIdentifier,
        array $memberResourceIdentifiers
    ): void {
        $this->resourceClassesToResourceGroupIdentifiersToMemberResourceIdentifiersMap[
            $resourceClass][$resourceGroupIdentifier] = $memberResourceIdentifiers;
    }
}
