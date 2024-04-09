<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

class GroupService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getGroup(string $identifier, array $options)
    {
    }

    public function getGroups(int $currentPageNumber, int $maxNumItemsPerPage, array $options)
    {
    }
}
