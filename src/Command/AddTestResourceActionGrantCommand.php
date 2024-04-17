<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Command;

use Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService;
use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class AddTestResourceActionGrantCommand extends Command
{
    private InternalResourceActionGrantService $resourceActionGrantService;
    private AuthorizationService $authorizationService;

    public function __construct(InternalResourceActionGrantService $resourceActionGrantService, AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->resourceActionGrantService = $resourceActionGrantService;
        $this->authorizationService = $authorizationService;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay-authorization:add-resource-action-grant');
        $this
            ->setDescription('Add a resource action grant for testing')
            ->addArgument('namespace', InputArgument::REQUIRED, 'namespace')
            ->addArgument('userIdentifier', InputArgument::REQUIRED, 'userIdentifier')
            ->addArgument('action', InputArgument::OPTIONAL, 'action: default: "manage"', InternalResourceActionGrantService::MANAGE_ACTION)
            ->addArgument('resourceIdentifier', InputArgument::OPTIONAL, 'identifier: default null', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespace = $input->getArgument('namespace');
        $resourceIdentifier = $input->getArgument('resourceIdentifier');
        $action = $input->getArgument('action');
        $userIdentifier = $input->getArgument('userIdentifier');

        $resourceActionGrant = new ResourceActionGrant();
        $resourceActionGrant->setNamespace($namespace);
        $resourceActionGrant->setResourceIdentifier($resourceIdentifier);
        $resourceActionGrant->setAction($action);
        $resourceActionGrant->setUserIdentifier($userIdentifier);

        $this->resourceActionGrantService->addResourceActionGrant($resourceActionGrant);

        return 0;
    }
}
