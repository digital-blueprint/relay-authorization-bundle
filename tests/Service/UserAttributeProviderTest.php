<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Rest\AvailableResourceClassActionsProvider;
use Dbp\Relay\AuthorizationBundle\Service\UserAttributeProvider;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\CoreBundle\User\UserAttributeException;

class UserAttributeProviderTest extends AbstractAuthorizationServiceTestCase
{
    private ?UserAttributeProvider $userAttributeProvider = null;

    protected function setUp(): void
    {
        parent::setUp();

        $availableResourceClassActionsProvider = new AvailableResourceClassActionsProvider(
            $this->internalResourceActionGrantService, $this->authorizationService);
        $this->userAttributeProvider = new UserAttributeProvider(
            $this->authorizationService, $availableResourceClassActionsProvider);
    }

    public function testHasAttribute(): void
    {
        $this->assertFalse($this->userAttributeProvider->hasUserAttribute(''));
        $this->assertFalse($this->userAttributeProvider->hasUserAttribute('foo'));
        $this->assertFalse($this->userAttributeProvider->hasUserAttribute('foo.'.AuthorizationService::MANAGE_ACTION));
        $this->assertFalse($this->userAttributeProvider->hasUserAttribute(self::TEST_RESOURCE_CLASS.'foo'));
        $this->assertFalse($this->userAttributeProvider->hasUserAttribute(self::TEST_RESOURCE_CLASS.'foo.bar'));
        $this->assertTrue($this->userAttributeProvider->hasUserAttribute(
            self::TEST_RESOURCE_CLASS.'.'.TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION));
        $this->assertTrue($this->userAttributeProvider->hasUserAttribute(self::TEST_RESOURCE_CLASS.'.'.AuthorizationService::MANAGE_ACTION));
        $this->assertTrue($this->userAttributeProvider->hasUserAttribute(self::TEST_RESOURCE_CLASS.'.foo.'.AuthorizationService::MANAGE_ACTION));
        $this->assertTrue($this->userAttributeProvider->hasUserAttribute(
            self::TEST_RESOURCE_CLASS.'.foo.'.TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION));
    }

    public function testGetAttributeUndefined(): void
    {
        $this->expectException(UserAttributeException::class);
        $this->expectExceptionCode(UserAttributeException::USER_ATTRIBUTE_UNDEFINED);
        $this->userAttributeProvider->getUserAttribute(self::CURRENT_USER_IDENTIFIER, 'foo');
    }

    /**
     * @throws UserAttributeException
     */
    public function testGetCollectionManageActionAttribute(): void
    {
        $attributeName = self::TEST_RESOURCE_CLASS.'.'.AuthorizationService::MANAGE_ACTION;
        $this->assertFalse($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));

        $userAttributes = $this->getDefaultUserAttributes();
        $userAttributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = true;
        $this->login(self::CURRENT_USER_IDENTIFIER, $userAttributes);

        $this->assertTrue($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));
    }

    /**
     * @throws UserAttributeException
     */
    public function testGetCollectionActionAttribute(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, null);

        $attributeName = self::TEST_RESOURCE_CLASS.'.'.TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION;
        $this->assertFalse($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::CREATE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));
    }

    /**
     * @throws UserAttributeException
     */
    public function testGetItemManageActionAttribute(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $attributeName = self::TEST_RESOURCE_CLASS.'.'.self::TEST_RESOURCE_IDENTIFIER.'.'.AuthorizationService::MANAGE_ACTION;
        $this->assertFalse($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));
    }

    /**
     * @throws UserAttributeException
     */
    public function testGetItemActionAttribute(): void
    {
        $authorizationResource = $this->testEntityManager->addAuthorizationResource(
            self::TEST_RESOURCE_CLASS, self::TEST_RESOURCE_IDENTIFIER);

        $attributeName = self::TEST_RESOURCE_CLASS.'.'.self::TEST_RESOURCE_IDENTIFIER.'.'.
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION;
        $this->assertFalse($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));

        $this->testEntityManager->addResourceActionGrant($authorizationResource,
            TestGetAvailableResourceClassActionsEventSubscriber::READ_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertTrue($this->userAttributeProvider->getUserAttribute(
            self::CURRENT_USER_IDENTIFIER, $attributeName));
    }

    protected function getDefaultUserAttributes(): array
    {
        $defaultUserAttributes = parent::getDefaultUserAttributes();
        $defaultUserAttributes['MAY_MANAGE_TEST_RESOURCE_COLLECTION'] = false;

        return $defaultUserAttributes;
    }

    protected function getTestConfig(): array
    {
        $testConfig = parent::getTestConfig();
        $testConfig[Configuration::RESOURCE_CLASSES] = [
            [
                Configuration::IDENTIFIER => self::TEST_RESOURCE_CLASS,
                Configuration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_MANAGE_TEST_RESOURCE_COLLECTION")',
            ],
        ];

        return $testConfig;
    }
}
