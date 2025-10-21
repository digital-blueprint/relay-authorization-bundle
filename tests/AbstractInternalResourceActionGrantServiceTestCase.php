<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Entity\AuthorizationResource;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestGetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\AuthorizationBundle\Tests\EventSubscriber\TestResourceActionGrantAddedEventSubscriber;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractInternalResourceActionGrantServiceTestCase extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = 'userIdentifier';
    protected const ANOTHER_USER_IDENTIFIER = 'anotherUserIdentifier';

    protected const TEST_RESOURCE_CLASS = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS;
    protected const TEST_COLLECTION_RESOURCE_CLASS = TestGetAvailableResourceClassActionsEventSubscriber::TEST_COLLECTION_RESOURCE_CLASS;
    protected const TEST_RESOURCE_CLASS_2 = TestGetAvailableResourceClassActionsEventSubscriber::TEST_RESOURCE_CLASS_2;

    protected const TEST_RESOURCE_IDENTIFIER = 'resourceIdentifier';
    protected const TEST_COLLECTION_RESOURCE_IDENTIFIER = 'collectionResourceIdentifier';
    protected const TEST_RESOURCE_IDENTIFIER_2 = 'resourceIdentifier_2';

    protected ?TestEntityManager $testEntityManager = null;
    protected InternalResourceActionGrantService $internalResourceActionGrantService;
    protected EventDispatcher $eventDispatcher;
    protected ?TestResourceActionGrantAddedEventSubscriber $testResourceActionGrantAddedEventSubscriber = null;

    protected function setUp(): void
    {
        // allow database data re-use when calling setUp multiple times
        $this->testEntityManager = $this->testEntityManager ?: new TestEntityManager(self::bootKernel()->getContainer());

        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addSubscriber(new TestGetAvailableResourceClassActionsEventSubscriber());
        $this->testResourceActionGrantAddedEventSubscriber = new TestResourceActionGrantAddedEventSubscriber();
        $this->eventDispatcher->addSubscriber($this->testResourceActionGrantAddedEventSubscriber);

        $this->internalResourceActionGrantService = new InternalResourceActionGrantService(
            $this->testEntityManager->getEntityManager(), $this->eventDispatcher);
        $this->internalResourceActionGrantService->setLogger(new NullLogger());
    }

    protected function tearDown(): void
    {
        $this->testEntityManager = null;
    }

    protected function selectWhere(array $results, callable $where, bool $passInKeyToo = false): array
    {
        return array_filter($results, $where, $passInKeyToo ? ARRAY_FILTER_USE_BOTH : 0);
    }

    protected function containsResource(array $resources, mixed $resource): bool
    {
        foreach ($resources as $res) {
            if ($resource->getIdentifier() === $res->getIdentifier()) {
                return true;
            }
        }

        return false;
    }

    protected function assertContainsResource(mixed $resource, array $resources): void
    {
        $this->assertTrue($this->containsResource($resources, $resource), 'resource array does not contain given resource');
    }

    protected function assertContainsResourceWhere(mixed $resource, array $resources, callable $criteria): void
    {
        foreach ($resources as $res) {
            if ($resource->getIdentifier() === $res->getIdentifier()) {
                $this->assertTrue($criteria($resource), 'resource does not match given criteria');

                return;
            }
        }

        $this->fail('resource array does not contain given resource');
    }

    protected function assertIsPermutationOf(array $array1, array $array2): void
    {
        $this->assertTrue(count($array1) === count($array2)
            && count($array1) === count(array_intersect($array1, $array2)), 'arrays are no permutations of each other');
    }

    protected function assertContainsResourceActionGrant(array $rags, ResourceActionGrant $ragExpected): void
    {
        $this->assertCount(1, $this->selectWhere($rags,
            function (ResourceActionGrant $rag) use ($ragExpected) {
                return $rag->getIdentifier() === $ragExpected->getIdentifier()
                    && $rag->getResourceClass() === $ragExpected->getResourceClass()
                    && $rag->getResourceIdentifier() === $ragExpected->getResourceIdentifier()
                    && $rag->getAction() === $ragExpected->getAction()
                    && $rag->getUserIdentifier() === $ragExpected->getUserIdentifier()
                    && $rag->getGroup() === $ragExpected->getGroup()
                    && $rag->getDynamicGroupIdentifier() === $ragExpected->getDynamicGroupIdentifier();
            }), (string) $ragExpected);
    }

    protected function assertContainsInheritedResourceActionGrant(array $rags,
        ResourceActionGrant $sourceRag, AuthorizationResource $effectiveResource): void
    {
        $this->assertCount(1, $this->selectWhere($rags,
            function (ResourceActionGrant $rag) use ($sourceRag, $effectiveResource) {
                return $rag->getIdentifier() === $sourceRag->getIdentifier().'_inherited'
                    && $rag->getResourceClass() === $effectiveResource->getResourceClass()
                    && $rag->getResourceIdentifier() === $effectiveResource->getResourceIdentifier()
                    && $rag->getAction() === $sourceRag->getAction()
                    && $rag->getUserIdentifier() === $sourceRag->getUserIdentifier()
                    && $rag->getGroup() === $sourceRag->getGroup()
                    && $rag->getDynamicGroupIdentifier() === $sourceRag->getDynamicGroupIdentifier();
            }), (string) $sourceRag);
    }
}
