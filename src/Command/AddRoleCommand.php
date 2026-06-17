<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Command;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class AddRoleCommand extends Command
{
    public function __construct(
        private readonly ResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:authorization:add-role');
        $this->setAliases(['dbp:relay-authorization:add-role']);
        $this->setDescription('Add a role');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $role = $this->resourceActionGrantService->addRole(
            [
                'en' => 'Submitter',
                'de' => 'Einreicher',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    'DbpRelayFormalizeForm',
                    'read',
                    ResourceActionGrantService::ITEM_ACTION_TYPE
                ),
                ResourceActionGrantService::createRoleAction(
                    'DbpRelayFormalizeSubmissionCollection',
                    'create_submissions',
                    ResourceActionGrantService::ITEM_ACTION_TYPE
                ),
            ]
        );

        $output->writeln('Role added successfully. UUID: '.$role->getIdentifier());

        return 0;
    }
}
