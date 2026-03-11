<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    // make sure this is called after all other MigratePostEventSubscribers, which might add resource actions that we need to update here.
    private const PRIORITY = -100;

    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => ['onMigratePostEvent', self::PRIORITY],
        ];
    }

    public function __construct(
        private AuthorizationService $authorizationService)
    {
    }

    public function onMigratePostEvent(MigratePostEvent $event): void
    {
        $output = $event->getOutput();
        try {
            $this->authorizationService->updateManageResourceCollectionPolicyGrants();
        } catch (\Throwable $throwable) {
            $output->writeln('Error updating manage resource collection policy grants: '.$throwable->getMessage());
        }
    }
}
