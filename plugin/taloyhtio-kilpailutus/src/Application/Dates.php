<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use DateTimeImmutable;
use DateTimeZone;

final class Dates
{
    /** Aikavyöhyke, jota käytetään, jos syötteessä ei ole aikavyöhykettä. */
    public const LOCAL_TIMEZONE = 'Europe/Helsinki';

    private function __construct()
    {
    }

    /** Päivämäärä muodossa VVVV-KK-PP. */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    /**
     * ISO 8601 -aika, esim. 2026-11-30T12:00 tai 2026-11-30T10:00:00Z.
     * Ilman aikavyöhykettä tulkitaan Suomen ajaksi. Palautetaan UTC:ssä.
     */
    public static function parseDateTime(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})?$/', $value)) {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($value, new DateTimeZone(self::LOCAL_TIMEZONE));
        } catch (\Exception) {
            return null;
        }
        // Hylkää olemattomat päivät (esim. 2026-02-30), jotka PHP muuten siirtäisi.
        if ($dt->format('Y-m-d') !== substr($value, 0, 10)) {
            return null;
        }

        return $dt->setTimezone(new DateTimeZone('UTC'));
    }

    public static function formatDateTime(?DateTimeImmutable $dt): ?string
    {
        return $dt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public static function formatDate(?DateTimeImmutable $dt): ?string
    {
        return $dt?->format('Y-m-d');
    }
}
