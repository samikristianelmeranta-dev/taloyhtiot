<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Exception;

/**
 * Kaikkien liiketoimintasääntövirheiden yläluokka. HTTP-tila kertoo
 * rajapintakerrokselle, miten virhe näytetään; ydin ei tunne WordPressiä.
 */
abstract class DomainError extends \RuntimeException
{
    abstract public function httpStatus(): int;

    abstract public function errorCode(): string;

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [];
    }
}
