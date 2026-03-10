<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

readonly class HealthCheck implements CheckInterface
{
    public function __construct(
        private ResourceActionGrantService $resourceActionGrantService)
    {
    }

    public function getName(): string
    {
        return 'authorization';
    }

    public function check(CheckOptions $options): array
    {
        $result = new CheckResult('Check if the DB connection works');

        $result->set(CheckResult::STATUS_SUCCESS);
        try {
            $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                AuthorizationService::GROUP_RESOURCE_CLASS,
                ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER
            );
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);
        }

        return [$result];
    }
}
