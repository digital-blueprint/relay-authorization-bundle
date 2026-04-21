<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Helper;

use Doctrine\DBAL\ParameterType;
use Symfony\Bridge\Doctrine\Types\AbstractUidType;
use Symfony\Component\Uid\Uuid;

class AuthorizationUuidBinaryType extends AbstractUidType
{
    public const NAME = 'relay_authorization_uuid_binary';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function getUidClass(): string
    {
        return Uuid::class;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }
}
