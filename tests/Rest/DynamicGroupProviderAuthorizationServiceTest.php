<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration;
use Dbp\Relay\AuthorizationBundle\Entity\DynamicGroup;
use Dbp\Relay\AuthorizationBundle\Rest\DynamicGroupProvider;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;

class DynamicGroupProviderAuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    private DataProviderTester $dynamicGroupProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new DynamicGroupProvider($this->authorizationService);
        $this->dynamicGroupProviderTester = DataProviderTester::create($provider,
            DynamicGroup::class,
            ['AuthorizationDynamicGroup:output']);
    }

    public function testGetItem(): void
    {
        $dynamicGroup = $this->dynamicGroupProviderTester->getItem('students');
        $this->assertEquals('students', $dynamicGroup->getIdentifier());
    }

    public function testGetItemNotFound(): void
    {
        $this->assertNull($this->dynamicGroupProviderTester->getItem('404'));
    }

    public function testGetCollection(): void
    {
        $dynamicGroups = $this->dynamicGroupProviderTester->getCollection();
        $this->assertCount(3, $dynamicGroups);
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('students')));
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('employees')));
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('researchers')));

        // test pagination:
        $dynamicGroupPage1 = $this->dynamicGroupProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $dynamicGroupPage1);

        $dynamicGroupPage2 = $this->dynamicGroupProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $dynamicGroupPage2);

        $dynamicGroups = array_merge($dynamicGroupPage1, $dynamicGroupPage2);
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('students')));
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('employees')));
        $this->assertTrue($this->containsResource($dynamicGroups, new DynamicGroup('researchers')));
    }

    protected function getTestConfig(): array
    {
        $config = parent::getTestConfig();
        $config[Configuration::DYNAMIC_GROUPS] = [
            [
                Configuration::IDENTIFIER => 'students',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_STUDENT")',
            ],
            [
                Configuration::IDENTIFIER => 'employees',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_EMPLOYEE")',
            ],
            [
                Configuration::IDENTIFIER => 'researchers',
                Configuration::IS_CURRENT_USER_GROUP_MEMBER_EXPRESSION => 'user.get("IS_RESEARCHER")',
            ],
        ];

        return $config;
    }
}
