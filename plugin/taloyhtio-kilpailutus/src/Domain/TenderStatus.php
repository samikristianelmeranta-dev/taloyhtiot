<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/**
 * Tallennettu tila. "Tarjousaika päättynyt" ei ole oma tilansa, vaan se
 * päätellään määräpäivästä. Näin tarjousten avautuminen ei riipu siitä,
 * ehtiikö ajastettu tehtävä (WP-Cron) päivittää tilan.
 */
enum TenderStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';
}
