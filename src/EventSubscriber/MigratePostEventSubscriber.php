<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => 'onMigratePostEvent',
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
