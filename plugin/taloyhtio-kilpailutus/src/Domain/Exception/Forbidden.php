<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Exception;

final class Forbidden extends DomainError
{
    public function httpStatus(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'tk_forbidden';
    }
}
