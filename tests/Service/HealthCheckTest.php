<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Service\HealthCheck;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheckTest extends AbstractAuthorizationServiceTestCase
{
    private ?HealthCheck $healthCheck = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthCheck = new HealthCheck(
            new ResourceActionGrantService($this->authorizationService)
        );
    }

    public function testHealthCheckSuccess(): void
    {
        $checkResults = $this->healthCheck->check(new CheckOptions());
        $this->assertCount(1, $checkResults);
        $checkResult = $checkResults[0];
        $this->assertEquals(CheckResult::STATUS_SUCCESS, $checkResult->getStatus());
    }

    public function testHealthCheckFailure(): void
    {
        $this->testEntityManager->prepareDBError();

        $checkResults = $this->healthCheck->check(new CheckOptions());
        $this->assertCount(1, $checkResults);
        $checkResult = $checkResults[0];
        $this->assertEquals(CheckResult::STATUS_FAILURE, $checkResult->getStatus());
    }
}
