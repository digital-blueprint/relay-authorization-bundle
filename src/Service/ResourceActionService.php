<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

class ResourceActionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getResourceAction(string $id, array $options)
    {
        return null;
    }

    public function getResourceActions(int $currentPageNumber, int $maxNumItemsPerPage, array $options): array
    {
        return [];
    }
}
