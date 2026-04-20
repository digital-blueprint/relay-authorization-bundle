<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Helper;

use Ramsey\Uuid\Doctrine\UuidBinaryType;

class AuthorizationUuidBinaryType extends UuidBinaryType
{
    public const NAME = 'relay_authorization_uuid_binary';
}
