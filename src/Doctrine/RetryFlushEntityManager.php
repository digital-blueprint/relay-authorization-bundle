<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Doctrine;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class RetryFlushEntityManager extends EntityManagerDecorator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?ManagerRegistry $managerRegistry,
        private readonly ?string $entityManagerName,
        private readonly int $maxNumRetries = 3,
        private readonly int $sleepForMilliseconds = 100
    ) {
        parent::__construct($this->entityManager);

        if ($this->maxNumRetries < 0) {
            throw new \InvalidArgumentException('maxNumRetries must be >= 0');
        }
        if ($this->sleepForMilliseconds < 0) {
            throw new \InvalidArgumentException('sleepForMilliseconds must be >= 0');
        }
    }

    /**
     * @throws RetryableException
     */
    public function flush(): void
    {
        $numTries = 0;
        $done = false;
        do {
            try {
                parent::flush();
                $done = true;
            } catch (RetryableException $exception) {
                if (++$numTries >= $this->maxNumRetries + 1) {
                    throw $exception;
                }
                usleep($this->sleepForMilliseconds * 1000);
                $this->resetEntityManager();
            }
        } while (false === $done);
    }

    private function resetEntityManager(): void
    {
        $entityManager = $this->managerRegistry->resetManager($this->entityManagerName);
        assert($entityManager instanceof EntityManagerInterface);
        $this->wrapped = $entityManager;
    }
}
