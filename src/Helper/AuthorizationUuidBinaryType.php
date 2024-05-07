<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Helper;

use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;

class AuthorizationUuidBinaryType extends UuidBinaryType
{
    public const NAME = 'relay_authorization_uuid_binary';

    public static function toBinaryUuid(string $stringUuid): string
    {
        return Uuid::fromString($stringUuid)->getBytes();
    }

    public static function toStringUuid(string $binaryUuid): string
    {
        return Uuid::fromBytes($binaryUuid)->toString();
    }

    public static function toBinaryUuids(array $stringUuids): array
    {
        return array_map(function (string $stringUuid): string {
            return self::toBinaryUuid($stringUuid);
        }, $stringUuids);
    }

    public static function toStringUuids(array $binaryUuids): array
    {
        return array_map(function (string $binaryUuid): string {
            return self::toStringUuid($binaryUuid);
        }, $binaryUuids);
    }
}
