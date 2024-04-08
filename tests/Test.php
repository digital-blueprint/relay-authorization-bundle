<?php

declare(strict_types=1);

namespace Dbp\Relay\AuhorizationBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class Test extends KernelTestCase
{
    public function testContainer()
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->assertNotNull($container);
    }
}
