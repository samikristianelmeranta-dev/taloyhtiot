<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

interface Clock
{
    /** Nykyhetki UTC-aikavyöhykkeessä. */
    public function now(): \DateTimeImmutable;
}
