<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Exception;

final class NotFound extends DomainError
{
    public function httpStatus(): int
    {
        return 404;
    }

    public function errorCode(): string
    {
        return 'tk_not_found';
    }
}
