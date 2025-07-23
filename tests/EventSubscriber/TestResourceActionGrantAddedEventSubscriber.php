<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestResourceActionGrantAddedEventSubscriber implements EventSubscriberInterface
{
    private ?ResourceActionGrantAddedEvent $event = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ResourceActionGrantAddedEvent::class => 'onResourceActionGrantAddedEvent',
        ];
    }

    public function onResourceActionGrantAddedEvent(ResourceActionGrantAddedEvent $resourceActionGrantAddedEvent): void
    {
        $this->event = $resourceActionGrantAddedEvent;
    }

    public function getEvent(): ?ResourceActionGrantAddedEvent
    {
        return $this->event;
    }
}
