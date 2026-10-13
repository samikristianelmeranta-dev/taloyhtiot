<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/** Remonttityypit. Lista on tarkoituksella laaja; käytössä olevia voi rajata asetuksella. */
final class RenovationType
{
    public const ALL = [
        'ikkunat' => 'Ikkunaremontti',
        'ovet' => 'Ovien uusiminen',
        'parvekkeet' => 'Parvekeremontti',
        'julkisivu' => 'Julkisivuremontti',
        'katto' => 'Kattoremontti',
        'linjasaneeraus' => 'Linjasaneeraus (putkiremontti)',
        'sahko' => 'Sähköremontti',
        'piha' => 'Piha- ja salaojatyöt',
        'hissi' => 'Hissin rakentaminen tai uusiminen',
        'muu' => 'Muu remontti',
    ];

    private function __construct()
    {
    }

    public static function isValid(string $slug): bool
    {
        return array_key_exists($slug, self::ALL);
    }
}
