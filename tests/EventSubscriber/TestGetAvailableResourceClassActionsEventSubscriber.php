<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestGetAvailableResourceClassActionsEventSubscriber implements EventSubscriberInterface
{
    public const TEST_RESOURCE_CLASS = 'resourceClass';

    public const TEST_COLLECTION_RESOURCE_CLASS = 'resourceClassCollection';

    public const TEST_RESOURCE_CLASS_2 = 'resourceClass_2';
    public const TEST_RESOURCE_CLASS_3 = 'resourceClass_3';

    public const READ_ACTION = 'read';
    public const WRITE_ACTION = 'write';
    public const UPDATE_ACTION = 'update';
    public const DELETE_ACTION = 'delete';
    public const CREATE_ACTION = 'create';

    public const TEST_RESOURCE_ITEM_ACTIONS = [
        self::READ_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        self::WRITE_ACTION => [
            'en' => 'Write',
            'de' => 'Schreiben',
        ],
        self::UPDATE_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        self::DELETE_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
    ];

    public const TEST_RESOURCE_COLLECTION_ACTIONS = [
        self::CREATE_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

    public const TEST_RESOURCE_2_ITEM_ACTIONS = [
        self::UPDATE_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
    ];

    public const TEST_RESOURCE_2_COLLECTION_ACTIONS = [
        self::READ_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
    ];

    public const TEST_RESOURCE_3_ITEM_ACTIONS = [
        self::WRITE_ACTION => [
            'en' => 'Write',
            'de' => 'Schreiben',
        ],
    ];

    public const TEST_RESOURCE_3_COLLECTION_ACTIONS = [
        self::READ_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        self::CREATE_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event): void
    {
        switch ($event->getResourceClass()) {
            case self::TEST_RESOURCE_CLASS:
            case self::TEST_COLLECTION_RESOURCE_CLASS:
                $event->setItemActions(self::TEST_RESOURCE_ITEM_ACTIONS);
                $event->setCollectionActions(self::TEST_RESOURCE_COLLECTION_ACTIONS);
                break;
            case self::TEST_RESOURCE_CLASS_2:
                $event->setItemActions(self::TEST_RESOURCE_2_ITEM_ACTIONS);
                $event->setCollectionActions(self::TEST_RESOURCE_2_COLLECTION_ACTIONS);
                break;
            case self::TEST_RESOURCE_CLASS_3:
                $event->setItemActions(self::TEST_RESOURCE_3_ITEM_ACTIONS);
                $event->setCollectionActions(self::TEST_RESOURCE_3_COLLECTION_ACTIONS);
                break;
        }
    }
}
