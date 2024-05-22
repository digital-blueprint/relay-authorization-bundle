<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestGetAvailableResourceClassActionsEventSubscriber implements EventSubscriberInterface
{
    public const TEST_RESOURCE_CLASS = 'TestResourceClass';

    public const TEST_RESOURCE_ITEM_ACTIONS = [
        'read',
        'update',
        'delete',
    ];

    public const TEST_RESOURCE_COLLECTION_ACTIONS = [
        'create',
        AuthorizationService::MANAGE_ACTION,
    ];

    public static function getSubscribedEvents()
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event): void
    {
        switch ($event->getResourceClass()) {
            case self::TEST_RESOURCE_CLASS:
                $event->setItemActions(self::TEST_RESOURCE_ITEM_ACTIONS);
                $event->setCollectionActions(self::TEST_RESOURCE_COLLECTION_ACTIONS);
                break;
            default:
                break;
        }
    }
}
