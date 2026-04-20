<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Helper;

use Symfony\Component\Uid\Uuid;

class UuidUtils
{
    public static function toBinaryUuid(string $stringUuid): string
    {
        return Uuid::fromString($stringUuid)->toBinary();
    }

    public static function toStringUuid(string $binaryUuid): string
    {
        return Uuid::fromBinary($binaryUuid)->toString();
    }

    public static function toBinaryUuids(array $stringUuids): array
    {
        return array_map(function (string $stringUuid): string {
            return UuidUtils::toBinaryUuid($stringUuid);
        }, $stringUuids);
    }

    public static function toStringUuids(array $binaryUuids): array
    {
        return array_map(function (string $binaryUuid): string {
            return UuidUtils::toStringUuid($binaryUuid);
        }, $binaryUuids);
    }
}
