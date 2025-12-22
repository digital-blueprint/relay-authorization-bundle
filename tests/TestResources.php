<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

class TestResources
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
        self::READ_ACTION => self::READ_NAMES,
        self::WRITE_ACTION => self::WRITE_NAMES,
        self::UPDATE_ACTION => self::UPDATE_NAMES,
        self::DELETE_ACTION => self::DELETE_NAMES,
    ];

    public const TEST_RESOURCE_COLLECTION_ACTIONS = [
        self::CREATE_ACTION => self::CREATE_NAMES,
    ];

    public const TEST_RESOURCE_2_ITEM_ACTIONS = [
        self::UPDATE_ACTION => self::UPDATE_NAMES,
        self::DELETE_ACTION => self::DELETE_NAMES,
    ];

    public const TEST_RESOURCE_2_COLLECTION_ACTIONS = [
        self::READ_ACTION => self::READ_NAMES,
        self::CREATE_ACTION => self::CREATE_NAMES,
    ];

    public const TEST_RESOURCE_3_ITEM_ACTIONS = [
        self::WRITE_ACTION => self::WRITE_NAMES,
    ];

    public const TEST_RESOURCE_3_COLLECTION_ACTIONS = [
        self::READ_ACTION => self::READ_NAMES,
        self::CREATE_ACTION => self::CREATE_NAMES,
    ];

    private const READ_NAMES = [
        'en' => 'Read',
        'de' => 'Lesen',
    ];
    private const WRITE_NAMES = [
        'en' => 'Write',
        'de' => 'Schreiben',
    ];
    private const UPDATE_NAMES = [
        'en' => 'Update',
        'de' => 'Aktualisieren',
    ];
    private const DELETE_NAMES = [
        'en' => 'Delete',
        'de' => 'Löschen',
    ];
    private const CREATE_NAMES = [
        'en' => 'Create',
        'de' => 'Erstellen',
    ];
}
