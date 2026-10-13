<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Exception;

final class Conflict extends DomainError
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'tk_conflict';
    }
}
