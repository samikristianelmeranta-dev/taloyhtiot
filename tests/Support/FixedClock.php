<?php

declare(strict_types=1);

namespace Kilpailutus\Tests\Support;

use Kilpailutus\Domain\Clock;

final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $now): void
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
    }
}
