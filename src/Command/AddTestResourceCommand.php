<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Command;

use Dbp\Relay\AuthorizationBundle\Service\InternalResourceActionGrantService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class AddTestResourceCommand extends Command
{
    public function __construct(
        private readonly InternalResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:authorization:add-resource');
        $this->setAliases(['dbp:relay-authorization:add-resource']);
        $this
            ->setDescription('Add a resource and a manage resource grant for testing')
            ->addArgument('resourceClass', InputArgument::REQUIRED, 'resourceClass')
            ->addArgument('userIdentifier', InputArgument::REQUIRED, 'userIdentifier')
            ->addArgument('resourceIdentifier', InputArgument::OPTIONAL, 'identifier: default null', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resourceClass = $input->getArgument('resourceClass');
        $resourceIdentifier = $input->getArgument('resourceIdentifier');
        $userIdentifier = $input->getArgument('userIdentifier');

        $this->resourceActionGrantService->addResourceAndManageResourceGrantFor(
            $resourceClass, $resourceIdentifier, $userIdentifier);

        return 0;
    }
}
