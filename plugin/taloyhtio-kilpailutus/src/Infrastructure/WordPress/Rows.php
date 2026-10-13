<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;

/** Muunnokset tietokannan UTC-aikojen ja olioiden välillä. */
final class Rows
{
    private function __construct()
    {
    }

    public static function toDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    public static function toDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        return DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) ?: null;
    }

    public static function fromDateTime(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function fromDate(?DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d');
    }
}
