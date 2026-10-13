<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/**
 * Palvelun loogiset roolit. WordPress-sovitin kääntää käyttäjän oikeudet
 * (capabilities) näiksi, joten ydin ei riipu WordPressin roolinimistä.
 */
final class Role
{
    /** Taloyhtiö tai isännöitsijä: luo kilpailutuksia ja valitsee urakoitsijan. */
    public const HOUSING_COMPANY = 'housing_company';

    /** Urakoitsija: jättää tarjouksia avoimiin kilpailutuksiin. */
    public const CONTRACTOR = 'contractor';

    /** Palvelun ylläpitäjä: näkee kilpailutukset mutta ei tarjouksia ennen määräpäivää. */
    public const ADMIN = 'admin';

    private function __construct()
    {
    }
}
