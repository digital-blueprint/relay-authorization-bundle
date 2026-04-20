<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests;

use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use PHPUnit\Framework\TestCase;

class UuidUtilsTest extends TestCase
{
    public function testToBinaryUuid(): void
    {
        $binaryUuid = UuidUtils::toBinaryUuid('019daaac-05ae-78d1-86f1-a3bac3b0c047');

        $this->assertSame("\x01\x9d\xaa\xac\x05\xae\x78\xd1\x86\xf1\xa3\xba\xc3\xb0\xc0\x47", $binaryUuid);
        $this->assertSame(16, strlen($binaryUuid));
    }

    public function testToStringUuid(): void
    {
        $binaryUuid = UuidUtils::toBinaryUuid('019daaac-05ae-78d1-86f1-a3bac3b0c047');
        $stringUuid = UuidUtils::toStringUuid($binaryUuid);

        $this->assertSame('019daaac-05ae-78d1-86f1-a3bac3b0c047', $stringUuid);
    }

    public function testToBinaryUuids(): void
    {
        $stringUuids = [
            '019daaac-05ae-78d1-86f1-a3bac3b0c047',
            '019daab8-8209-7698-ac07-bdb150021ba4',
        ];

        $binaryUuids = UuidUtils::toBinaryUuids($stringUuids);

        $this->assertCount(2, $binaryUuids);
        foreach ($binaryUuids as $binaryUuid) {
            $this->assertIsString($binaryUuid);
            $this->assertSame(16, strlen($binaryUuid));
        }
    }

    public function testToStringUuids(): void
    {
        $stringUuids = [
            '019daaac-05ae-78d1-86f1-a3bac3b0c047',
            '019daab8-8209-7698-ac07-bdb150021ba4',
        ];

        $binaryUuids = UuidUtils::toBinaryUuids($stringUuids);
        $resultStringUuids = UuidUtils::toStringUuids($binaryUuids);

        $this->assertSame($stringUuids, $resultStringUuids);
    }
}
